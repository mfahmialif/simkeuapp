<?php

use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\ValidateBsiCallback;
use App\Http\Middleware\ValidateSimkeuv2ApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'bsi.callback' => ValidateBsiCallback::class,
            'simkeuv2.apikey' => ValidateSimkeuv2ApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

$app->usePublicPath(dirname(__DIR__, 2).'/public_html');

return $app;
