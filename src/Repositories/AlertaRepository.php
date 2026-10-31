<?php

declare(strict_types=1);

namespace Monitor\Repositories;

use Monitor\Connection;

/**
 * Acceso a datos de la tabla alertas_umbrales.
 */
final class AlertaRepository
{
    public static function listarPorHost(int $idHost): array
    {
        $consulta = Connection::obtener()->prepare(
            'SELECT id_alerta, id_host, metrica, valor_limite, activo
             FROM alertas_umbrales WHERE id_host = :id_host ORDER BY id_alerta DESC'
        );
        $consulta->execute(['id_host' => $idHost]);

        return $consulta->fetchAll();
    }

    /**
     * id_usuario dueño de la alerta (a través de su host), o null si no existe.
     */
    public static function buscarPropietario(int $idAlerta): ?int
    {
        $consulta = Connection::obtener()->prepare(
            'SELECT h.id_usuario
             FROM alertas_umbrales a INNER JOIN hosts h ON h.id_host = a.id_host
             WHERE a.id_alerta = :id_alerta LIMIT 1'
        );
        $consulta->execute(['id_alerta' => $idAlerta]);
        $fila = $consulta->fetch();

        return $fila ? (int) $fila['id_usuario'] : null;
    }

    public static function crear(int $idHost, string $metrica, float $valorLimite): int
    {
        $pdo = Connection::obtener();
        $pdo->prepare(
            'INSERT INTO alertas_umbrales (id_host, metrica, valor_limite) VALUES (:id_host, :metrica, :valor_limite)'
        )->execute(['id_host' => $idHost, 'metrica' => $metrica, 'valor_limite' => $valorLimite]);

        return (int) $pdo->lastInsertId();
    }

    public static function actualizar(int $idAlerta, float $valorLimite, bool $activo): void
    {
        Connection::obtener()->prepare(
            'UPDATE alertas_umbrales SET valor_limite = :valor_limite, activo = :activo WHERE id_alerta = :id_alerta'
        )->execute(['valor_limite' => $valorLimite, 'activo' => $activo ? 1 : 0, 'id_alerta' => $idAlerta]);
    }

    public static function eliminar(int $idAlerta): void
    {
        Connection::obtener()->prepare('DELETE FROM alertas_umbrales WHERE id_alerta = :id_alerta')
            ->execute(['id_alerta' => $idAlerta]);
    }
}
