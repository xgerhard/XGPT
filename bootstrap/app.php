<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The per-IP throttle runs before CommandController, so its catch-all never sees this.
        // Whatever /api/command returns is posted into chat, so answer with a plain sentence.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/command')) {
                return response('XGPT is getting a lot of requests right now. Try again in a moment.', 200, [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                ]);
            }
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
