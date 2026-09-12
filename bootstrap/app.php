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
    // Nova's own artisan commands live under app/NovaAI/Console/Commands -
    // outside the app/Console/Commands path Laravel auto-discovers by
    // default - so php artisan nova:health needs this one explicit
    // registration line, the same minimal-bootstrap-touch precedent
    // already used for the 'nova_memory' DB connection and provider (see
    // app/NovaAI/README.md, Stage 4).
    ->withCommands([
        \App\NovaAI\Console\Commands\NovaHealthCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'super-admin' => \App\Http\Middleware\EnsureSuperAdmin::class,
            'module' => \App\Http\Middleware\EnsureModulePermission::class,
            'nova.admin' => \App\NovaAI\Http\Middleware\EnsureNovaAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
