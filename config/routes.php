<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\HomeController;
use App\Controllers\SurpriseController;
use App\Core\Router;

$router = new Router();

$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [HomeController::class, 'showLogin']);
$router->post('/login', [HomeController::class, 'login']);
$router->get('/logout', [HomeController::class, 'logout']);

$router->get('/surprises/live', [SurpriseController::class, 'live']);
$router->get('/surprises', [SurpriseController::class, 'all'], ['auth']);
$router->get('/surprises/testEmail', [SurpriseController::class, 'testEmail']);
$router->post('/surprises/testEmail', [SurpriseController::class, 'testEmail']);
$router->put('/surprises/{id}/viewed', [SurpriseController::class, 'viewed']);

$router->get('/admin', [AdminController::class, 'index'], ['auth']);
$router->get('/admin/surprise/{id}/notify', [SurpriseController::class, 'notify'], ['auth']);
$router->post('/admin/surprise', [SurpriseController::class, 'create'], ['auth']);
$router->put('/admin/surprise/{id}', [SurpriseController::class, 'update'], ['auth']);
$router->delete('/admin/surprise/{id}', [SurpriseController::class, 'delete'], ['auth']);

return $router;
