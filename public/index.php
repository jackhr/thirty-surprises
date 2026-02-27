<?php

declare(strict_types=1);

use App\Core\Request;

require_once dirname(__DIR__) . '/config/bootstrap.php';

$request = Request::capture();
$router = require dirname(__DIR__) . '/config/routes.php';
$router->dispatch($request);
