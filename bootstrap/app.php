<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureWorkspaceContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'active-user' => EnsureActiveUser::class,
            'workspace-context' => EnsureWorkspaceContext::class,
            'super-admin' => EnsureSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
