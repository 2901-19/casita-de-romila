<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\RegistrarLanzador;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            RegistrarLanzador::class,
            EnsureUserIsActive::class,
        ]);

        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            RegistrarLanzador::class,
        );

        $middleware->validateCsrfTokens(except: [
            'lanzador/cerrar-sesion',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
