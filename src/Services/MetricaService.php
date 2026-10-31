<?php

declare(strict_types=1);

namespace Monitor\Services;

use Monitor\Errors;
use Monitor\Repositories\HostRepository;
use Monitor\Repositories\LecturaRepository;
use Monitor\Sessions\Session;
use Monitor\Tools;
use Monitor\ValueValidation;

/**
 * Servicios de métricas/lecturas: lectura actual, historial e ingreso
 * de lecturas desde el colector de Python.
 */
final class MetricaService
{
    public static function actual(): never
    {
        $usuario = Session::exigirSesion();
        $idHost = ValueValidation::validarId(ValueValidation::validarObligatorio($_GET['id_host'] ?? null));
        HostService::exigirHostDelUsuario($idHost, $usuario['id_usuario']);

        Tools::bien(LecturaRepository::ultimaDeHost($idHost));
    }

    /**
     * Historial de lecturas de un host en un rango de fechas.
     *
     * No se devuelven las lecturas crudas (un equipo genera ~17,000 al
     * día): el rango se divide en `max_puntos` intervalos iguales y de
     * cada uno se devuelve el PROMEDIO de CPU/RAM/Disco.
     *
     * Parámetros GET: id_host, inicio, fin (Y-m-d) y max_puntos
     * (opcional, 50-2000, por defecto 600).
     */
    public static function historial(): never
    {
        $usuario = Session::exigirSesion();
        $idHost = ValueValidation::validarId(ValueValidation::validarObligatorio($_GET['id_host'] ?? null));
        HostService::exigirHostDelUsuario($idHost, $usuario['id_usuario']);

        $inicio = ValueValidation::validarFecha(ValueValidation::validarObligatorio($_GET['inicio'] ?? null));
        $fin = ValueValidation::validarFecha(ValueValidation::validarObligatorio($_GET['fin'] ?? null));
        ValueValidation::validarRangoDeFechas($inicio, $fin);

        $maxPuntos = 600;
        if (isset($_GET['max_puntos'])) {
            $maxPuntos = ValueValidation::validarId($_GET['max_puntos']);
            if ($maxPuntos < 50 || $maxPuntos > 2000) {
                Errors::VALIDACION->responder();
            }
        }

        $segundosRango = (new \DateTimeImmutable($fin))->modify('+1 day')->getTimestamp()
            - (new \DateTimeImmutable($inicio))->getTimestamp();
        $intervalo = max(1, (int) ceil($segundosRango / $maxPuntos));

        $filas = LecturaRepository::promediosPorIntervalo($idHost, $inicio, $fin, $intervalo);

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
     * Usado por el colector de Python (NO por el Frontend, sin sesión de
     * usuario). El host se autentica con id_host + ip_mac.
     */
    public static function ingresar(): never
    {
        $datos = Tools::leerJsonBody();
        $idHost = ValueValidation::validarId(ValueValidation::validarObligatorio($datos['id_host'] ?? null));
        $mac = ValueValidation::validarMac(ValueValidation::validarObligatorio($datos['mac'] ?? null));
        $cpu = ValueValidation::validarNumeroEnRango($datos['cpu'] ?? null);
        $ram = ValueValidation::validarNumeroEnRango($datos['ram'] ?? null);
        $disco = ValueValidation::validarNumeroEnRango($datos['disco'] ?? null);

        if (!HostRepository::existeConMac($idHost, $mac)) {
            Errors::NO_ENCONTRADO->responder();
        }

        Tools::bien(['id_lectura' => LecturaRepository::crear($idHost, $cpu, $ram, $disco)], 201);
    }
}
