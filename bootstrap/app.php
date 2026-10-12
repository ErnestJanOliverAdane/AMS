<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Console\Scheduling\Schedule;
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
            'role' => EnsureUserHasRole::class,
        ]);

        // Signed-in users who open /login or /register are sent to their own dashboard.
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('appointments:send-reminders')->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
