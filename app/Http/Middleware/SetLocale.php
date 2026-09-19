<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) ($request->header('Accept-Language') ?? $request->query('lang', ''));
        $locale = strtolower(substr(trim($header), 0, 2));

        if (in_array($locale, config('savdodaftar.locales', ['uz', 'ru']), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
