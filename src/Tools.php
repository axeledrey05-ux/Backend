<?php

declare(strict_types=1);

namespace Monitor;

/**
 * Funciones auxiliares reutilizables en todo el Backend, relacionadas
 * con la petición y la respuesta HTTP (JSON, lectura del body, CORS).
 * No clasifica ni responde errores (eso es Errors.php) ni valida datos
 * (eso es ValueValidation.php); solo se encarga de la mecánica de I/O.
 */
class Tools
{
    /**
     * Envía una respuesta JSON con el formato estándar de la API
     * ({"estado", "respuesta", "error"?}) y termina la ejecución.
     */
    public static function enviarJson(string $estado, mixed $respuesta = null, ?array $error = null, int $codigoHttp = 200): never
    {
        http_response_code($codigoHttp);

        $payload = [
            'estado' => $estado,
            'respuesta' => $respuesta,
        ];

        if ($error !== null) {
            $payload['error'] = $error;
        }

        echo json_encode($payload);
        exit;
    }

    public static function bien(mixed $respuesta = null, int $codigoHttp = 200): never
    {
        self::enviarJson('bien', $respuesta, null, $codigoHttp);
    }

    /**
     * Lee y decodifica el cuerpo JSON de la petición actual.
     * Si el body no es JSON válido, devuelve un arreglo vacío
     * (las validaciones posteriores se encargan de rechazarlo).
     */
    public static function leerJsonBody(): array
    {
        $crudo = file_get_contents('php://input');
        $datos = json_decode($crudo, true);

        return is_array($datos) ? $datos : [];
    }

    /**
     * Configura las cabeceras CORS necesarias para que el Frontend
     * (en otro origen) pueda comunicarse con el Backend, incluyendo
     * el envío de la cookie de sesión.
     */
    public static function configurarCors(): void
    {
        $origen = getenv('FRONTEND_ORIGIN') ?: 'http://localhost:5173';

        header("Access-Control-Allow-Origin: {$origen}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
