<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);

        app()->setLocale($locale);

        if (app()->bound('translator')) {
            app('translator')->setLocale($locale);
        }

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        if (Auth::check() && Auth::user()->preferred_language_code) {
            return Auth::user()->preferred_language_code;
        }

        if ($request->hasSession() && $request->session()->has('locale')) {
            return $request->session()->get('locale');
        }

        return config('app.locale', 'pt-PT');
    }
}
