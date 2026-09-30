<?php

declare(strict_types=1);

namespace Monitor;

/**
 * Catálogo centralizado de errores del Backend.
 *
 * Cada caso representa un error posible y su valor es un JSON con:
 *   - Type: 1 = error del cliente (datos/permiso/sesión), 2 = error del servidor.
 *   - Category: subtipo dentro de ese Type (ver tabla abajo).
 *   - Description: mensaje legible para depuración (no necesariamente el que
 *     ve el usuario final del Frontend).
 *
 * Categorías usadas:
 *   1 = validación de datos        4 = registro no encontrado
 *   2 = parámetro faltante          5 = base de datos
 *   3 = autenticación / sesión      6 = permisos (usuario sin autorización)
 *   7 = servicio/ruta inexistente   8 = el registro ya existe (duplicado)
 *   9 = error interno / desconocido
 */
enum Errors: string
{
    case VALIDACION = '{"Error":{"Type":1,"Category":1,"Description":"Uno o mas campos enviados no son validos."}}';

    case PARAMETRO_FALTANTE = '{"Error":{"Type":1,"Category":2,"Description":"Falta un parametro obligatorio en la peticion."}}';

    case NO_AUTENTICADO = '{"Error":{"Type":1,"Category":3,"Description":"No hay una sesion iniciada."}}';

    case SESION_INVALIDA = '{"Error":{"Type":1,"Category":3,"Description":"La sesion no es valida o ya expiro."}}';

    case SIN_PERMISO = '{"Error":{"Type":1,"Category":6,"Description":"El usuario no tiene permiso para realizar esta accion."}}';

    case NO_ENCONTRADO = '{"Error":{"Type":1,"Category":4,"Description":"El registro solicitado no existe."}}';

    case YA_EXISTE = '{"Error":{"Type":1,"Category":8,"Description":"Ya existe un registro con esos datos."}}';

    case SERVICIO_NO_ENCONTRADO = '{"Error":{"Type":1,"Category":7,"Description":"El servicio o ruta solicitada no existe."}}';

    case ERROR_BASE_DATOS = '{"Error":{"Type":2,"Category":5,"Description":"Error al comunicarse con la base de datos."}}';

    case ERROR_INTERNO = '{"Error":{"Type":2,"Category":9,"Description":"Error interno del servidor. Revisa el registro de errores."}}';

    /**
     * Devuelve el objeto {Type, Category, Description} listo para
     * colocarse en el campo "error" de la respuesta JSON.
     */
    public function payload(): array
    {
        $decodificado = json_decode($this->value, true);

        return $decodificado['Error'];
    }

    /**
     * Código de estado HTTP apropiado para este error.
     */
    public function httpCode(): int
    {
        return match ($this) {
            self::VALIDACION, self::PARAMETRO_FALTANTE => 422,
            self::NO_AUTENTICADO, self::SESION_INVALIDA => 401,
            self::SIN_PERMISO => 403,
            self::NO_ENCONTRADO, self::SERVICIO_NO_ENCONTRADO => 404,
            self::YA_EXISTE => 409,
            self::ERROR_BASE_DATOS, self::ERROR_INTERNO => 500,
        };
    }
}
