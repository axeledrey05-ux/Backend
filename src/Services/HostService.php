<?php

declare(strict_types=1);

namespace Monitor\Services;

use Monitor\Errors;
use Monitor\Repositories\HostRepository;
use Monitor\Sessions\Session;
use Monitor\Tools;
use Monitor\ValueValidation;

/**
 * Servicios de hosts (equipos monitoreados; cada uno pertenece a un usuario).
 */
final class HostService
{
    public static function listar(): never
    {
        $usuario = Session::exigirSesion();

        Tools::bien(HostRepository::listarPorUsuario($usuario['id_usuario']));
    }

    public static function registrar(): never
    {
        $usuario = Session::exigirSesion();

        $datos = Tools::leerJsonBody();
        $nombreHost = ValueValidation::validarTexto(ValueValidation::validarObligatorio($datos['nombre_host'] ?? null), 2, 100);
        $ip = ValueValidation::validarIp(ValueValidation::validarObligatorio($datos['ip_direccion'] ?? null));
        $mac = ValueValidation::validarMac(ValueValidation::validarObligatorio($datos['ip_mac'] ?? null));

        if (HostRepository::existeMac($mac)) {
            Errors::YA_EXISTE->responder();
        }

        $idHost = HostRepository::crear($usuario['id_usuario'], $nombreHost, $ip, $mac);

        Tools::bien([
            'id_host' => $idHost,
            'nombre_host' => $nombreHost,
            'ip_direccion' => $ip,
            'ip_mac' => $mac,
        ], 201);
    }

    public static function actualizar(): never
    {
        $usuario = Session::exigirSesion();

        $datos = Tools::leerJsonBody();
        $idHost = ValueValidation::validarId(ValueValidation::validarObligatorio($datos['id_host'] ?? null));
        self::exigirHostDelUsuario($idHost, $usuario['id_usuario']);

        $nombreHost = ValueValidation::validarTexto(ValueValidation::validarObligatorio($datos['nombre_host'] ?? null), 2, 100);
        $ip = ValueValidation::validarIp(ValueValidation::validarObligatorio($datos['ip_direccion'] ?? null));
        $mac = strtolower(ValueValidation::validarMac(ValueValidation::validarObligatorio($datos['ip_mac'] ?? null)));

        // La MAC es única: no puede coincidir con la de OTRO equipo.
        if (HostRepository::existeMac($mac, $idHost)) {
            Errors::YA_EXISTE->responder();
        }

        HostRepository::actualizar($idHost, $nombreHost, $ip, $mac);

        Tools::bien([
            'id_host' => $idHost,
            'nombre_host' => $nombreHost,
            'ip_direccion' => $ip,
            'ip_mac' => $mac,
        ]);
    }

    /**
     * Verifica que el host exista Y pertenezca al usuario en sesión.
     * Si no, corta la ejecución con el error apropiado. Lo reutilizan
     * MetricaService y AlertaService.
     */
    public static function exigirHostDelUsuario(int $idHost, int $idUsuario): void
    {
        $propietario = HostRepository::buscarPropietario($idHost);

        if ($propietario === null) {
            Errors::NO_ENCONTRADO->responder();
        }

        if ($propietario !== $idUsuario) {
            Errors::SIN_PERMISO->responder();
        }
    }
}
