<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Monitor\Errors;
use Monitor\Services;
use Monitor\Tools;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    Tools::mal(Errors::SERVICIO_NO_ENCONTRADO);
}

Services::eliminarAlerta();
