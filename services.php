<?php

declare(strict_types=1);

namespace Monitor;

use Monitor\Sessions\Session;

/**
 * Punto donde se concentran los servicios/endpoints principales del
 * Backend. Cada método público corresponde a UN endpoint y sigue
 * siempre el mismo flujo:
 *
 *   Petición -> este método -> ValueValidation -> Session (si aplica)
 *   -> Connection -> Base de Datos -> este método -> Tools (Respuesta)
 *
 * Los métodos de este archivo NUNCA devuelven datos a quien los llama:
 * ellos mismos terminan la petición con Tools::bien()/Tools::mal().
 * Esto mantiene a los archivos de entrada (login.php, hosts/listar.php,
 * etc.) como simples "routers" de una sola línea.
 */
class Services
{
    // -----------------------------------------------------------------
    // Autenticación
    // -----------------------------------------------------------------

    public static function login(): never
    {
        $datos = Tools::leerJsonBody();
        $email = ValueValidation::validarCorreo(ValueValidation::validarObligatorio($datos['email'] ?? null));
        $contrasena = ValueValidation::validarTexto(ValueValidation::validarObligatorio($datos['contrasena'] ?? null), 1, 255);

        $pdo = Connection::obtener();
        $consulta = $pdo->prepare('SELECT id_usuario, nombre, email, contrasena FROM usuarios WHERE email = :email LIMIT 1');
        $consulta->execute(['email' => $email]);
        $usuario = $consulta->fetch();

        if (!$usuario) {
            Tools::mal(Errors::NO_ENCONTRADO);
        }

        if (!password_verify($contrasena, $usuario['contrasena'])) {
            Tools::mal(Errors::VALIDACION);
        }

        Session::iniciarSesionUsuario($usuario);

        Tools::bien([
            'id_usuario' => $usuario['id_usuario'],
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email'],
        ]);
    }

    public static function registro(): never
    {
        $datos = Tools::leerJsonBody();
        $nombre = ValueValidation::validarTexto(ValueValidation::validarObligatorio($datos['nombre'] ?? null), 2, 100);
        $email = ValueValidation::validarCorreo(ValueValidation::validarObligatorio($datos['email'] ?? null));
        $contrasena = ValueValidation::validarTexto(ValueValidation::validarObligatorio($datos['contrasena'] ?? null), 6, 255);

        $pdo = Connection::obtener();

        $existe = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE email = :email LIMIT 1');
        $existe->execute(['email' => $email]);

        if ($existe->fetch()) {
            Tools::mal(Errors::YA_EXISTE);
        }

        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
        $insertar = $pdo->prepare('INSERT INTO usuarios (nombre, contrasena, email) VALUES (:nombre, :contrasena, :email)');
        $insertar->execute(['nombre' => $nombre, 'contrasena' => $hash, 'email' => $email]);

        Tools::bien([
            'id_usuario' => (int) $pdo->lastInsertId(),
            'nombre' => $nombre,
            'email' => $email,
        ], 201);
    }

    public static function sesionActual(): never
    {
        Tools::bien(Session::exigirSesion());
    }

    public static function logout(): never
    {
        Session::cerrarSesion();
        Tools::bien(null);
    }

    // -----------------------------------------------------------------
    // Hosts (equipos monitoreados, cada uno pertenece a un usuario)
    // -----------------------------------------------------------------

    public static function listarHosts(): never
    {
        $usuario = Session::exigirSesion();

        $pdo = Connection::obtener();
        $consulta = $pdo->prepare(
            'SELECT id_host, nombre_host, ip_direccion, ip_mac
             FROM hosts WHERE id_usuario = :id_usuario ORDER BY nombre_host'
        );
        $consulta->execute(['id_usuario' => $usuario['id_usuario']]);

        Tools::bien($consulta->fetchAll());
    }

    public static function registrarHost(): never
    {
        $usuario = Session::exigirSesion();

        $datos = Tools::leerJsonBody();
        $nombreHost = ValueValidation::validarTexto(ValueValidation::validarObligatorio($datos['nombre_host'] ?? null), 2, 100);
        $ip = ValueValidation::validarIp(ValueValidation::validarObligatorio($datos['ip_direccion'] ?? null));
        $mac = ValueValidation::validarMac(ValueValidation::validarObligatorio($datos['ip_mac'] ?? null));

        $pdo = Connection::obtener();

        $duplicado = $pdo->prepare('SELECT id_host FROM hosts WHERE ip_mac = :mac LIMIT 1');
        $duplicado->execute(['mac' => $mac]);

        if ($duplicado->fetch()) {
            Tools::mal(Errors::YA_EXISTE);
        }

        $insertar = $pdo->prepare(
            'INSERT INTO hosts (id_usuario, nombre_host, ip_direccion, ip_mac)
             VALUES (:id_usuario, :nombre_host, :ip, :mac)'
        );
        $insertar->execute([
            'id_usuario' => $usuario['id_usuario'],
            'nombre_host' => $nombreHost,
            'ip' => $ip,
            'mac' => $mac,
        ]);

        Tools::bien([
            'id_host' => (int) $pdo->lastInsertId(),
            'nombre_host' => $nombreHost,
            'ip_direccion' => $ip,
            'ip_mac' => $mac,
        ], 201);
    }

    /**
     * Verifica que un host exista Y pertenezca al usuario en sesión.
     * Si no, corta la ejecución con el error apropiado. Se usa en
     * todos los servicios que reciben un id_host desde el Frontend.
     */
    private static function exigirHostDelUsuario(int $idHost, int $idUsuario): void
    {
        $pdo = Connection::obtener();
        $consulta = $pdo->prepare('SELECT id_usuario FROM hosts WHERE id_host = :id_host LIMIT 1');
        $consulta->execute(['id_host' => $idHost]);
        $host = $consulta->fetch();

        if (!$host) {
            Tools::mal(Errors::NO_ENCONTRADO);
        }

        if ((int) $host['id_usuario'] !== $idUsuario) {
            Tools::mal(Errors::SIN_PERMISO);
        }
    }

    // -----------------------------------------------------------------
    // Métricas / lecturas
    // -----------------------------------------------------------------

    public static function metricaActual(): never
    {
        $usuario = Session::exigirSesion();
        $idHost = ValueValidation::validarId(ValueValidation::validarObligatorio($_GET['id_host'] ?? null));
        self::exigirHostDelUsuario($idHost, $usuario['id_usuario']);

        $pdo = Connection::obtener();
        $consulta = $pdo->prepare(
            'SELECT cpu, ram, disco, fecha_hora FROM lecturas
             WHERE id_host = :id_host ORDER BY fecha_hora DESC LIMIT 1'
        );
        $consulta->execute(['id_host' => $idHost]);

        Tools::bien($consulta->fetch() ?: null);
    }

    /**
     * Historial de lecturas de un host en un rango de fechas.
     *
     * Un equipo que manda una lectura cada 5 s genera ~17,000 filas por
     * día, y mandarlas todas al navegador lo hace lento. Por eso NO se
     * devuelven las lecturas crudas: el rango completo se divide en
     * `max_puntos` intervalos iguales y de cada uno se devuelve el
     * PROMEDIO de CPU/RAM/Disco. Así siempre se cubre TODO el rango
     * (nada se "corta"), pero la respuesta pesa unos pocos KB sin
     * importar cuántos datos tenga el equipo.
     *
     * Parámetros GET: id_host, inicio, fin (Y-m-d) y max_puntos
     * (opcional, 50-2000, por defecto 600).
     */
    public static function historial(): never
    {
        $usuario = Session::exigirSesion();
        $idHost = ValueValidation::validarId(ValueValidation::validarObligatorio($_GET['id_host'] ?? null));
        self::exigirHostDelUsuario($idHost, $usuario['id_usuario']);

        $inicio = ValueValidation::validarFecha(ValueValidation::validarObligatorio($_GET['inicio'] ?? null));
        $fin = ValueValidation::validarFecha(ValueValidation::validarObligatorio($_GET['fin'] ?? null));
        ValueValidation::validarRangoDeFechas($inicio, $fin);

        $maxPuntos = 600;
        if (isset($_GET['max_puntos'])) {
            $maxPuntos = ValueValidation::validarId($_GET['max_puntos']);
            if ($maxPuntos < 50 || $maxPuntos > 2000) {
                Tools::mal(Errors::VALIDACION);
            }
        }

        // Tamaño (en segundos) de cada intervalo. Es un entero calculado
        // aquí mismo (no viene del usuario), por eso puede ir directo en
        // el SQL sin riesgo de inyección.
        $segundosRango = (new \DateTimeImmutable($fin))->modify('+1 day')->getTimestamp()
            - (new \DateTimeImmutable($inicio))->getTimestamp();
        $intervalo = max(1, (int) ceil($segundosRango / $maxPuntos));

        $pdo = Connection::obtener();
        $consulta = $pdo->prepare(
            "SELECT FROM_UNIXTIME(cubeta * {$intervalo}) AS fecha_hora,
                    ROUND(AVG(cpu), 2) AS cpu,
                    ROUND(AVG(ram), 2) AS ram,
                    ROUND(AVG(disco), 2) AS disco,
                    COUNT(*) AS lecturas
             FROM (
                 SELECT FLOOR(UNIX_TIMESTAMP(fecha_hora) / {$intervalo}) AS cubeta, cpu, ram, disco
                 FROM lecturas
                 WHERE id_host = :id_host AND fecha_hora >= :inicio
                   AND fecha_hora < DATE_ADD(:fin, INTERVAL 1 DAY)
             ) AS agrupadas
             GROUP BY cubeta
             ORDER BY cubeta ASC"
        );
        $consulta->execute(['id_host' => $idHost, 'inicio' => $inicio, 'fin' => $fin]);
        $filas = $consulta->fetchAll();

        $totalLecturas = 0;
        foreach ($filas as &$fila) {
            $totalLecturas += (int) $fila['lecturas'];
            unset($fila['lecturas']);
        }
        unset($fila);

        Tools::bien([
            'intervalo_segundos' => $intervalo,
            'total_lecturas' => $totalLecturas,
            'datos' => $filas,
        ]);
    }

    /**
     * Usado por el colector de Python (NO por el Frontend, no requiere
     * sesión de usuario). El host se autentica con la combinación
     * id_host + ip_mac, que debe coincidir con lo registrado en la BD.
     */
    public static function ingresarLectura(): never
    {
        $datos = Tools::leerJsonBody();
        $idHost = ValueValidation::validarId(ValueValidation::validarObligatorio($datos['id_host'] ?? null));
        $mac = ValueValidation::validarMac(ValueValidation::validarObligatorio($datos['mac'] ?? null));
        $cpu = ValueValidation::validarNumeroEnRango($datos['cpu'] ?? null);
        $ram = ValueValidation::validarNumeroEnRango($datos['ram'] ?? null);
        $disco = ValueValidation::validarNumeroEnRango($datos['disco'] ?? null);

        $pdo = Connection::obtener();
        $consulta = $pdo->prepare('SELECT id_host FROM hosts WHERE id_host = :id_host AND ip_mac = :mac LIMIT 1');
        $consulta->execute(['id_host' => $idHost, 'mac' => $mac]);

        if (!$consulta->fetch()) {
            Tools::mal(Errors::NO_ENCONTRADO);
        }

        $insertar = $pdo->prepare('INSERT INTO lecturas (id_host, cpu, ram, disco) VALUES (:id_host, :cpu, :ram, :disco)');
        $insertar->execute(['id_host' => $idHost, 'cpu' => $cpu, 'ram' => $ram, 'disco' => $disco]);

        Tools::bien(['id_lectura' => (int) $pdo->lastInsertId()], 201);
    }

    // -----------------------------------------------------------------
    // Alertas / umbrales
    // -----------------------------------------------------------------

    public static function listarAlertas(): never
    {
        $usuario = Session::exigirSesion();
        $idHost = ValueValidation::validarId(ValueValidation::validarObligatorio($_GET['id_host'] ?? null));
        self::exigirHostDelUsuario($idHost, $usuario['id_usuario']);

        $pdo = Connection::obtener();
        $consulta = $pdo->prepare(
            'SELECT id_alerta, id_host, metrica, valor_limite, activo
             FROM alertas_umbrales WHERE id_host = :id_host ORDER BY id_alerta DESC'
        );
        $consulta->execute(['id_host' => $idHost]);

        Tools::bien($consulta->fetchAll());
    }

    public static function crearAlerta(): never
    {
        $usuario = Session::exigirSesion();

        $datos = Tools::leerJsonBody();
        $idHost = ValueValidation::validarId(ValueValidation::validarObligatorio($datos['id_host'] ?? null));
        self::exigirHostDelUsuario($idHost, $usuario['id_usuario']);

        $metrica = ValueValidation::validarEnLista($datos['metrica'] ?? null, ['cpu', 'ram', 'disco']);
        $valorLimite = ValueValidation::validarNumeroEnRango($datos['valor_limite'] ?? null);

        $pdo = Connection::obtener();
        $insertar = $pdo->prepare(
            'INSERT INTO alertas_umbrales (id_host, metrica, valor_limite) VALUES (:id_host, :metrica, :valor_limite)'
        );
        $insertar->execute(['id_host' => $idHost, 'metrica' => $metrica, 'valor_limite' => $valorLimite]);

        Tools::bien(['id_alerta' => (int) $pdo->lastInsertId()], 201);
    }

    /**
     * Confirma que una alerta exista y pertenezca (a través de su host)
     * al usuario en sesión. Devuelve la fila de la alerta.
     */
    private static function exigirAlertaDelUsuario(int $idAlerta, int $idUsuario): array
    {
        $pdo = Connection::obtener();
        $consulta = $pdo->prepare(
            'SELECT a.id_alerta, h.id_usuario
             FROM alertas_umbrales a INNER JOIN hosts h ON h.id_host = a.id_host
             WHERE a.id_alerta = :id_alerta LIMIT 1'
        );
        $consulta->execute(['id_alerta' => $idAlerta]);
        $alerta = $consulta->fetch();

        if (!$alerta) {
            Tools::mal(Errors::NO_ENCONTRADO);
        }

        if ((int) $alerta['id_usuario'] !== $idUsuario) {
            Tools::mal(Errors::SIN_PERMISO);
        }

        return $alerta;
    }

    public static function actualizarAlerta(): never
    {
        $usuario = Session::exigirSesion();

        $datos = Tools::leerJsonBody();
        $idAlerta = ValueValidation::validarId(ValueValidation::validarObligatorio($datos['id_alerta'] ?? null));
        self::exigirAlertaDelUsuario($idAlerta, $usuario['id_usuario']);

        $valorLimite = ValueValidation::validarNumeroEnRango($datos['valor_limite'] ?? null);
        $activo = ValueValidation::validarBooleano($datos['activo'] ?? null);

        $pdo = Connection::obtener();
        $actualizar = $pdo->prepare(
            'UPDATE alertas_umbrales SET valor_limite = :valor_limite, activo = :activo WHERE id_alerta = :id_alerta'
        );
        $actualizar->execute([
            'valor_limite' => $valorLimite,
            'activo' => $activo ? 1 : 0,
            'id_alerta' => $idAlerta,
        ]);

        Tools::bien(['id_alerta' => $idAlerta]);
    }

    public static function eliminarAlerta(): never
    {
        $usuario = Session::exigirSesion();

        $datos = Tools::leerJsonBody();
        $idAlerta = ValueValidation::validarId(ValueValidation::validarObligatorio($datos['id_alerta'] ?? null));
        self::exigirAlertaDelUsuario($idAlerta, $usuario['id_usuario']);

        $pdo = Connection::obtener();
        $eliminar = $pdo->prepare('DELETE FROM alertas_umbrales WHERE id_alerta = :id_alerta');
        $eliminar->execute(['id_alerta' => $idAlerta]);

        Tools::bien(['id_alerta' => $idAlerta]);
    }
}
