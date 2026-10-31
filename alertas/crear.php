<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Monitor\Errors;
use Monitor\Services\AlertaService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Errors::SERVICIO_NO_ENCONTRADO->responder();
}

AlertaService::crear();
