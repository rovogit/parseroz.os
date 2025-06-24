<?php

use App\Core\Router;
use App\Controllers\HomeController;

$router = new Router();

$router->addRoute('/', HomeController::class, 'index', 'GET');
$router->addRoute('/parse', HomeController::class, 'parse', 'GET');
$router->addRoute('/parse', HomeController::class, 'parse', 'POST');

return $router;