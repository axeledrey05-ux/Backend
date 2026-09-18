<?php

declare(strict_types=1);

namespace Monitor;

/**
 * Responsable de establecer y reutilizar la conexión con la Base de
 * Datos (MySQL, vía PDO).
 *
 * Se analizaron y se obtienen desde config/config.php (que a su vez
 * los lee de variables de entorno): tipo de motor (mysql), host,
 * puerto, usuario, contraseña y nombre de la base de datos.
 *
 * El manejo de errores de conexión: si PDO no logra conectar (por
 * ejemplo, MySQL aún no está listo, o las credenciales son
 * incorrectas), se captura la excepción, se registra el detalle real
 * en el log del servidor (nunca se expone al cliente) y se responde
 * con Errors::ERROR_BASE_DATOS.
 */
class Connection
{
    private static ?\PDO $instancia = null;

    /**
     * Devuelve la conexión PDO activa, creándola la primera vez que
     * se necesita (patrón singleton simple: una sola conexión por
     * petición HTTP, no una nueva por cada consulta).
     */
    public static function obtener(): \PDO
    {
        if (self::$instancia instanceof \PDO) {
            return self::$instancia;
        }

        $configuracion = require __DIR__ . '/config/config.php';
        $db = $configuracion['db'];

        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $db['driver'],
            $db['host'],
            $db['port'],
            $db['name']
        );

        try {
            self::$instancia = new \PDO($dsn, $db['user'], $db['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
        } catch (\PDOException $excepcion) {
            error_log('Error de conexion a la base de datos: ' . $excepcion->getMessage());
            Tools::mal(Errors::ERROR_BASE_DATOS);
        }

        return self::$instancia;
    }
}
