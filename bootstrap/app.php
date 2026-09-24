<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AdminAuthenticate;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', apiPrefix: 'api/v1', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [\App\Http\Middleware\SetLocale::class]);
        $middleware->alias(['admin' => AdminAuthenticate::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport([\App\Exceptions\ApiException::class]);
        $exceptions->shouldRenderJsonWhen(fn (\Illuminate\Http\Request $request) => $request->is('api/*') || $request->expectsJson());
    })->create();
