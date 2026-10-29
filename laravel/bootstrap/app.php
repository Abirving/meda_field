<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['staff' => \App\Http\Middleware\Staff::class,'mfa'=>\App\Http\Middleware\RequireMfa::class,'onboarded'=>\App\Http\Middleware\Onboarded::class]);
        $middleware->append(\App\Http\Middleware\ProductionSafety::class);
        $middleware->web(append:[\App\Http\Middleware\SessionGuard::class]);
        $middleware->trustProxies(at: array_filter(explode(',',(string)env('TRUSTED_PROXIES',''))));
        $middleware->redirectGuestsTo('/login');
    })->withExceptions(function (Exceptions $exceptions) {})->create();
