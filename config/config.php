<?php

declare(strict_types=1);

/**
 * Configuración general del Backend.
 *
 * NINGÚN valor sensible está escrito aquí: todo viene de variables de
 * entorno definidas en docker-compose.yml. Este archivo solo define
 * los valores por defecto para desarrollo local si la variable no
 * existe (útil si alguien corre el backend fuera de Docker).
 *
 * Si necesitas cambiar una credencial real (por ejemplo, para un
 * ambiente que no sea el de pruebas del proyecto), NO la escribas
 * aquí: créala como variable de entorno en tu propio docker-compose
 * override o en un archivo .env que NO se sube a git (ver README).
 */
return [
    'db' => [
        'driver' => 'mysql',
        'host' => getenv('DB_HOST') ?: 'db',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'monitor_db',
        'user' => getenv('DB_USER') ?: 'monitor_user',
        'password' => getenv('DB_PASSWORD') ?: 'monitor_pass',
    ],
    'app' => [
        'frontend_origin' => getenv('FRONTEND_ORIGIN') ?: 'http://localhost:5173',
    ],
];
