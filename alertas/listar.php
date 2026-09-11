<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Monitor\Errors;
use Monitor\Services;
use Monitor\Tools;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Tools::mal(Errors::SERVICIO_NO_ENCONTRADO);
}

Services::listarAlertas();
