<?php

declare(strict_types=1);

namespace Monitor\Repositories;

use Monitor\Connection;

/**
 * Acceso a datos de la tabla usuarios (todo el SQL de usuarios vive aquí).
 */
final class UsuarioRepository
{
    public static function buscarPorEmail(string $email): ?array
    {
        $consulta = Connection::obtener()->prepare(
            'SELECT id_usuario, nombre, email, contrasena FROM usuarios WHERE email = :email LIMIT 1'
        );
        $consulta->execute(['email' => $email]);

        return $consulta->fetch() ?: null;
    }

    public static function crear(string $nombre, string $email, string $hash): int
    {
        $pdo = Connection::obtener();
        $pdo->prepare('INSERT INTO usuarios (nombre, contrasena, email) VALUES (:nombre, :contrasena, :email)')
            ->execute(['nombre' => $nombre, 'contrasena' => $hash, 'email' => $email]);

        return (int) $pdo->lastInsertId();
    }
}
