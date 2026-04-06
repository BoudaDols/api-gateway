<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use App\Http\Middleware\LogRequestMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'jwt' => \App\Http\Middleware\JwtMiddleware::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
        ]);
        $middleware->appendToGroup('api', LogRequestMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ThrottleRequestsException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please slow down.',
            ], 429);
        });
    })->create();
