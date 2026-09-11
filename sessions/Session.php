<?php

declare(strict_types=1);

namespace Monitor\Sessions;

use Monitor\Errors;
use Monitor\Tools;

/**
 * Responsable de toda la lógica de sesión del usuario: creación,
 * cookie de sesión, validación, lectura y cierre.
 *
 * Se usan sesiones nativas de PHP (session_start/$_SESSION). La
 * información real (id_usuario, nombre, email) se guarda en el
 * archivo de sesión del lado del servidor; la cookie que recibe el
 * navegador (PHPSESSID) NO contiene esos datos, solo un identificador
 * aleatorio que apunta a ese archivo. Por eso:
 *   - httponly=true: JavaScript en el navegador no puede leer la cookie
 *     (mitiga robo de sesión por XSS).
 *   - samesite=Lax: el navegador no envía la cookie en peticiones
 *     iniciadas desde otros sitios (mitiga CSRF básico).
 *   - session_regenerate_id(true) al iniciar sesión: genera un ID nuevo
 *     y evita "session fixation" (que alguien fije de antemano el ID
 *     de sesión de la víctima).
 */
class Session
{
    private const CLAVE_USUARIO_ID = 'id_usuario';

    private const CLAVE_NOMBRE = 'nombre';

    private const CLAVE_EMAIL = 'email';

    /**
     * Arranca la sesión de PHP (si aún no está iniciada) con una
     * configuración de cookie segura.
     */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0, // la cookie expira al cerrar el navegador
                'path' => '/',
                'samesite' => 'Lax',
                'httponly' => true,
            ]);
            session_start();
        }
    }

    /**
     * Crea la sesión de un usuario ya autenticado (después de validar
     * su correo/contraseña en services.php). Regenera el ID de sesión
     * para evitar session fixation.
     */
    public static function iniciarSesionUsuario(array $usuario): void
    {
        self::iniciar();
        session_regenerate_id(true);

        $_SESSION[self::CLAVE_USUARIO_ID] = $usuario['id_usuario'];
        $_SESSION[self::CLAVE_NOMBRE] = $usuario['nombre'];
        $_SESSION[self::CLAVE_EMAIL] = $usuario['email'];
    }

    /**
     * Indica si la sesión actual corresponde a un usuario autenticado.
     */
    public static function hayUsuarioAutenticado(): bool
    {
        self::iniciar();

        return isset($_SESSION[self::CLAVE_USUARIO_ID]);
    }

    /**
     * Devuelve los datos del usuario en sesión, o null si no hay ninguno.
     */
    public static function usuarioActual(): ?array
    {
        self::iniciar();

        if (!self::hayUsuarioAutenticado()) {
            return null;
        }

        return [
            'id_usuario' => $_SESSION[self::CLAVE_USUARIO_ID],
            'nombre' => $_SESSION[self::CLAVE_NOMBRE],
            'email' => $_SESSION[self::CLAVE_EMAIL],
        ];
    }

    /**
     * Corta la ejecución con un error si no hay sesión iniciada;
     * de lo contrario, devuelve los datos del usuario en sesión.
     * Los servicios que requieren estar autenticado llaman a este
     * método al principio, antes de tocar la Base de Datos.
     */
    public static function exigirSesion(): array
    {
        $usuario = self::usuarioActual();

        if ($usuario === null) {
            Tools::mal(Errors::NO_AUTENTICADO);
        }

        return $usuario;
    }

    /**
     * Destruye por completo la sesión actual: limpia $_SESSION,
     * expira la cookie del navegador y borra el archivo de sesión
     * del servidor.
     */
    public static function cerrarSesion(): void
    {
        self::iniciar();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parametros['path'],
                $parametros['domain'],
                $parametros['secure'],
                $parametros['httponly']
            );
        }

        session_destroy();
    }
}
