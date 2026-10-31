<?php

declare(strict_types=1);

namespace Monitor\Services;

use Monitor\Errors;
use Monitor\Repositories\AlertaRepository;
use Monitor\Sessions\Session;
use Monitor\Tools;
use Monitor\ValueValidation;

/**
 * Servicios de alertas/umbrales por host.
 */
final class AlertaService
{
    public static function listar(): never
    {
        $usuario = Session::exigirSesion();
        $idHost = ValueValidation::validarId(ValueValidation::validarObligatorio($_GET['id_host'] ?? null));
        HostService::exigirHostDelUsuario($idHost, $usuario['id_usuario']);

        Tools::bien(AlertaRepository::listarPorHost($idHost));
    }

    public static function crear(): never
    {
        $usuario = Session::exigirSesion();

        $datos = Tools::leerJsonBody();
        $idHost = ValueValidation::validarId(ValueValidation::validarObligatorio($datos['id_host'] ?? null));
        HostService::exigirHostDelUsuario($idHost, $usuario['id_usuario']);

        $metrica = ValueValidation::validarEnLista($datos['metrica'] ?? null, ['cpu', 'ram', 'disco']);
        $valorLimite = ValueValidation::validarNumeroEnRango($datos['valor_limite'] ?? null);

        Tools::bien(['id_alerta' => AlertaRepository::crear($idHost, $metrica, $valorLimite)], 201);
    }

    public static function actualizar(): never
    {
        $usuario = Session::exigirSesion();

        $datos = Tools::leerJsonBody();
        $idAlerta = ValueValidation::validarId(ValueValidation::validarObligatorio($datos['id_alerta'] ?? null));
        self::exigirAlertaDelUsuario($idAlerta, $usuario['id_usuario']);

        $valorLimite = ValueValidation::validarNumeroEnRango($datos['valor_limite'] ?? null);
        $activo = ValueValidation::validarBooleano($datos['activo'] ?? null);

        AlertaRepository::actualizar($idAlerta, $valorLimite, $activo);

        Tools::bien(['id_alerta' => $idAlerta]);
    }

    public static function eliminar(): never
    {
        $usuario = Session::exigirSesion();

        $datos = Tools::leerJsonBody();
        $idAlerta = ValueValidation::validarId(ValueValidation::validarObligatorio($datos['id_alerta'] ?? null));
        self::exigirAlertaDelUsuario($idAlerta, $usuario['id_usuario']);

        AlertaRepository::eliminar($idAlerta);

        Tools::bien(['id_alerta' => $idAlerta]);
    }

    /**
     * Confirma que la alerta exista y pertenezca (a través de su host)
     * al usuario en sesión.
     */
    private static function exigirAlertaDelUsuario(int $idAlerta, int $idUsuario): void
    {
        $propietario = AlertaRepository::buscarPropietario($idAlerta);

        if ($propietario === null) {
            Errors::NO_ENCONTRADO->responder();
        }

        if ($propietario !== $idUsuario) {
            Errors::SIN_PERMISO->responder();
        }
    }
}
