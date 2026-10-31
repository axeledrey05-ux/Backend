<?php

declare(strict_types=1);

namespace Monitor\Repositories;

use Monitor\Connection;

/**
 * Acceso a datos de la tabla lecturas (CPU/RAM/Disco de cada host).
 */
final class LecturaRepository
{
    public static function ultimaDeHost(int $idHost): ?array
    {
        $consulta = Connection::obtener()->prepare(
            'SELECT cpu, ram, disco, fecha_hora FROM lecturas
             WHERE id_host = :id_host ORDER BY fecha_hora DESC LIMIT 1'
        );
        $consulta->execute(['id_host' => $idHost]);

        return $consulta->fetch() ?: null;
    }

    /**
     * Promedio de CPU/RAM/Disco por cada intervalo de $intervalo segundos.
     * $intervalo es un entero calculado por el servicio (no viene del
     * usuario), por eso puede ir directo en el SQL sin riesgo de inyección.
     */
    public static function promediosPorIntervalo(int $idHost, string $inicio, string $fin, int $intervalo): array
    {
        $consulta = Connection::obtener()->prepare(
            "SELECT FROM_UNIXTIME(cubeta * {$intervalo}) AS fecha_hora,
                    ROUND(AVG(cpu), 2) AS cpu,
                    ROUND(AVG(ram), 2) AS ram,
                    ROUND(AVG(disco), 2) AS disco,
                    COUNT(*) AS lecturas
             FROM (
                 SELECT FLOOR(UNIX_TIMESTAMP(fecha_hora) / {$intervalo}) AS cubeta, cpu, ram, disco
                 FROM lecturas
                 WHERE id_host = :id_host AND fecha_hora >= :inicio
                   AND fecha_hora < DATE_ADD(:fin, INTERVAL 1 DAY)
             ) AS agrupadas
             GROUP BY cubeta
             ORDER BY cubeta ASC"
        );
        $consulta->execute(['id_host' => $idHost, 'inicio' => $inicio, 'fin' => $fin]);

        return $consulta->fetchAll();
    }

    public static function crear(int $idHost, float $cpu, float $ram, float $disco): int
    {
        $pdo = Connection::obtener();
        $pdo->prepare('INSERT INTO lecturas (id_host, cpu, ram, disco) VALUES (:id_host, :cpu, :ram, :disco)')
            ->execute(['id_host' => $idHost, 'cpu' => $cpu, 'ram' => $ram, 'disco' => $disco]);

        return (int) $pdo->lastInsertId();
    }
}
