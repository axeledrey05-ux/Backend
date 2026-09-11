<?php

declare(strict_types=1);

namespace Monitor;

/**
 * Concentra todas las validaciones de valores recibidos por el Backend,
 * para que ningún servicio implemente su propia validación por separado.
 *
 * Cada función recibe el valor "crudo" (normalmente de un body JSON o de
 * $_GET) y, si es válido, devuelve la versión ya tipada y lista para usar.
 * Si NO es válido, la función corta la ejecución llamando a Tools::mal()
 * con el error apropiado — el código que llama no necesita revisar un
 * booleano de retorno, simplemente usa el valor que recibe de vuelta.
 */
class ValueValidation
{
    /**
     * Valida un identificador de base de datos (entero positivo).
     * $permitirCero permite el valor 0 en los casos donde tenga sentido
     * (por defecto no, porque los PK autoincrementales empiezan en 1).
     */
    public static function validarId(mixed $valor, bool $permitirCero = false): int
    {
        if (!is_numeric($valor) || (string) (int) $valor !== (string) $valor) {
            Tools::mal(Errors::VALIDACION);
        }

        $entero = (int) $valor;
        $minimo = $permitirCero ? 0 : 1;

        if ($entero < $minimo) {
            Tools::mal(Errors::VALIDACION);
        }

        return $entero;
    }

    /**
     * Valida que un valor obligatorio haya llegado (no sea null ni cadena vacía).
     */
    public static function validarObligatorio(mixed $valor): mixed
    {
        if ($valor === null || $valor === '') {
            Tools::mal(Errors::PARAMETRO_FALTANTE);
        }

        return $valor;
    }

    /**
     * Valida una cadena de texto con longitud mínima y máxima.
     */
    public static function validarTexto(mixed $valor, int $minimo = 1, int $maximo = 255): string
    {
        if (!is_string($valor)) {
            Tools::mal(Errors::VALIDACION);
        }

        $limpio = trim($valor);
        $longitud = mb_strlen($limpio);

        if ($longitud < $minimo || $longitud > $maximo) {
            Tools::mal(Errors::VALIDACION);
        }

        return $limpio;
    }

    /**
     * Valida formato de correo electrónico.
     */
    public static function validarCorreo(mixed $valor): string
    {
        if (!is_string($valor) || filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            Tools::mal(Errors::VALIDACION);
        }

        return $valor;
    }

    /**
     * Valida un número (entero o decimal) dentro de un rango cerrado.
     * Se usa para porcentajes de CPU/RAM/Disco (0-100 por defecto).
     */
    public static function validarNumeroEnRango(mixed $valor, float $min = 0, float $max = 100): float
    {
        if (!is_numeric($valor) || (float) $valor < $min || (float) $valor > $max) {
            Tools::mal(Errors::VALIDACION);
        }

        return (float) $valor;
    }

    /**
     * Valida un valor booleano explícito (true/false, no "truthy").
     */
    public static function validarBooleano(mixed $valor): bool
    {
        if (!is_bool($valor)) {
            Tools::mal(Errors::VALIDACION);
        }

        return $valor;
    }

    /**
     * Valida que el valor esté dentro de una lista fija de opciones
     * permitidas (ej. la métrica de una alerta: cpu/ram/disco).
     */
    public static function validarEnLista(mixed $valor, array $listaPermitida): string
    {
        if (!is_string($valor) || !in_array($valor, $listaPermitida, true)) {
            Tools::mal(Errors::VALIDACION);
        }

        return $valor;
    }

    /**
     * Valida una fecha con un formato específico (por defecto Y-m-d,
     * el que manda el <input type="date"> del Frontend).
     */
    public static function validarFecha(mixed $valor, string $formato = 'Y-m-d'): string
    {
        if (!is_string($valor)) {
            Tools::mal(Errors::VALIDACION);
        }

        $fecha = \DateTime::createFromFormat($formato, $valor);

        if ($fecha === false || $fecha->format($formato) !== $valor) {
            Tools::mal(Errors::VALIDACION);
        }

        return $valor;
    }

    /**
     * Valida que una fecha inicial no sea posterior a una fecha final
     * (ambas ya validadas individualmente con validarFecha()).
     */
    public static function validarRangoDeFechas(string $inicio, string $fin): void
    {
        if ($inicio > $fin) {
            Tools::mal(Errors::VALIDACION);
        }
    }

    /**
     * Valida formato de dirección IP (v4 o v6).
     */
    public static function validarIp(mixed $valor): string
    {
        if (!is_string($valor) || filter_var($valor, FILTER_VALIDATE_IP) === false) {
            Tools::mal(Errors::VALIDACION);
        }

        return $valor;
    }

    /**
     * Valida formato de dirección MAC (ej. 02:42:ac:14:00:0b).
     */
    public static function validarMac(mixed $valor): string
    {
        if (!is_string($valor) || preg_match('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', $valor) !== 1) {
            Tools::mal(Errors::VALIDACION);
        }

        return $valor;
    }
}
