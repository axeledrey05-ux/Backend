<?php

declare(strict_types=1);

namespace Monitor\Repositories;

use Monitor\Connection;

/**
 * Acceso a datos de la tabla hosts (equipos monitoreados).
 */
final class HostRepository
{
    public static function listarPorUsuario(int $idUsuario): array
    {
        $consulta = Connection::obtener()->prepare(
            'SELECT id_host, nombre_host, ip_direccion, ip_mac
             FROM hosts WHERE id_usuario = :id_usuario ORDER BY nombre_host'
        );
        $consulta->execute(['id_usuario' => $idUsuario]);

        return $consulta->fetchAll();
    }

    /**
     * id_usuario dueño del host, o null si el host no existe.
     */
    public static function buscarPropietario(int $idHost): ?int
    {
        $consulta = Connection::obtener()->prepare('SELECT id_usuario FROM hosts WHERE id_host = :id_host LIMIT 1');
        $consulta->execute(['id_host' => $idHost]);
        $fila = $consulta->fetch();

        return $fila ? (int) $fila['id_usuario'] : null;
    }

    /**
     * La MAC es única. $excluirId permite que un host conserve la suya
     * al editarse (se ignora a sí mismo en la comparación).
     */
    public static function existeMac(string $mac, ?int $excluirId = null): bool
    {
        if ($excluirId === null) {
            $consulta = Connection::obtener()->prepare('SELECT id_host FROM hosts WHERE ip_mac = :mac LIMIT 1');
            $consulta->execute(['mac' => $mac]);
        } else {
            $consulta = Connection::obtener()->prepare(
                'SELECT id_host FROM hosts WHERE ip_mac = :mac AND id_host <> :id_host LIMIT 1'
            );
            $consulta->execute(['mac' => $mac, 'id_host' => $excluirId]);
        }

        return (bool) $consulta->fetch();
    }

    /**
     * Autenticación del colector Python: id_host + MAC deben coincidir.
     */
    public static function existeConMac(int $idHost, string $mac): bool
    {
        $consulta = Connection::obtener()->prepare(
            'SELECT id_host FROM hosts WHERE id_host = :id_host AND ip_mac = :mac LIMIT 1'
        );
        $consulta->execute(['id_host' => $idHost, 'mac' => $mac]);

        return (bool) $consulta->fetch();
    }

    public static function crear(int $idUsuario, string $nombre, string $ip, string $mac): int
    {
        $pdo = Connection::obtener();
        $pdo->prepare(
            'INSERT INTO hosts (id_usuario, nombre_host, ip_direccion, ip_mac)
             VALUES (:id_usuario, :nombre_host, :ip, :mac)'
        )->execute(['id_usuario' => $idUsuario, 'nombre_host' => $nombre, 'ip' => $ip, 'mac' => $mac]);

        return (int) $pdo->lastInsertId();
    }

    public static function actualizar(int $idHost, string $nombre, string $ip, string $mac): void
    {
        Connection::obtener()->prepare(
            'UPDATE hosts SET nombre_host = :nombre_host, ip_direccion = :ip, ip_mac = :mac WHERE id_host = :id_host'
        )->execute(['nombre_host' => $nombre, 'ip' => $ip, 'mac' => $mac, 'id_host' => $idHost]);
    }
}
