<?php

declare(strict_types=1);

/**
 * Punto de arranque común para todo endpoint del Backend.
 * Cada archivo de servicio (auth/login.php, hosts/listar.php, etc.)
 * empieza con: require_once __DIR__ . '/bootstrap.php'; (o '../bootstrap.php'
 * si el archivo está un nivel más adentro).
 */

require_once __DIR__ . '/vendor/autoload.php';

use Monitor\Errors;
use Monitor\Tools;

Errors::registrarManejadores();
Tools::configurarCors();
