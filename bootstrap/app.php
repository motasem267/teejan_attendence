<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, $request) {
            \Illuminate\Support\Facades\Log::channel('single')->error(
                '[DEBUG-TRAP] '.get_class($e).': '.$e->getMessage(),
                ['file' => $e->getFile().':'.$e->getLine()]
            );
        });
    })->create();
