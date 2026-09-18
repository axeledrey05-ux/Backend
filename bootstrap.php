<?php

declare(strict_types=1);

/**
 * Punto de arranque común para todo endpoint del Backend.
 * Cada archivo de servicio (login.php, hosts/listar.php, etc.)
 * empieza con: require_once __DIR__ . '/bootstrap.php'; (o '../bootstrap.php'
 * si el archivo está un nivel más adentro).
 */

require_once __DIR__ . '/vendor/autoload.php';

use Monitor\Tools;

Tools::registrarManejadores();
Tools::configurarCors();
