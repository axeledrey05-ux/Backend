<?php

declare(strict_types=1);

namespace Monitor\Services;

use Monitor\Errors;
use Monitor\Repositories\UsuarioRepository;
use Monitor\Sessions\Session;
use Monitor\Tools;
use Monitor\ValueValidation;

/**
 * Servicios de autenticación: login, registro, sesión actual y logout.
 * Flujo: validar -> repositorio (SQL) -> sesión -> respuesta.
 */
final class AuthService
{
    public static function login(): never
    {
        $datos = Tools::leerJsonBody();
        $email = ValueValidation::validarCorreo(ValueValidation::validarObligatorio($datos['email'] ?? null));
        $contrasena = ValueValidation::validarTexto(ValueValidation::validarObligatorio($datos['contrasena'] ?? null), 1, 255);

        $usuario = UsuarioRepository::buscarPorEmail($email);

        if ($usuario === null) {
            Errors::NO_ENCONTRADO->responder();
        }

        if (!password_verify($contrasena, $usuario['contrasena'])) {
            Errors::VALIDACION->responder();
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

        if (UsuarioRepository::buscarPorEmail($email) !== null) {
            Errors::YA_EXISTE->responder();
        }

        $idUsuario = UsuarioRepository::crear($nombre, $email, password_hash($contrasena, PASSWORD_DEFAULT));

        Tools::bien(['id_usuario' => $idUsuario, 'nombre' => $nombre, 'email' => $email], 201);
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
}
