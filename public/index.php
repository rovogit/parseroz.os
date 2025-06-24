<?php

require_once __DIR__ . '/../vendor/autoload.php';

$router = require_once __DIR__ . '/../app/Routes.php';

$app = new App\Core\Application($router);
$app->run();