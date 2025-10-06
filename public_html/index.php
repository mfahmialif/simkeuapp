<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

require_once __DIR__.'/../simkeu.app/app/helpers.php';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../simkeu.app/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../simkeu.app/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../simkeu.app/bootstrap/app.php';

$app->handleRequest(Request::capture());
