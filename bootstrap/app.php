<?php

use App\Http\Middleware\EnsureSuperAdminWeb;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'super.admin' => EnsureSuperAdminWeb::class,
        ]);

        // Web admin panel: guests go to its login page and signed-in users
        // hitting the login page go to the dashboard. API requests keep
        // getting JSON 401s (see shouldRenderJsonWhen below).
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
