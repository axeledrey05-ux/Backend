<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Monitor\Errors;
use Monitor\Services\AuthService;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Errors::SERVICIO_NO_ENCONTRADO->responder();
}

AuthService::sesionActual();
