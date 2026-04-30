<?php

namespace App\Providers;

use App\Domain\Localization\DatabaseTranslationLoader;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\Translator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->resolving('translator', function ($translator, Application $app) {
            $loader = new DatabaseTranslationLoader($app['files'], $app['path.lang']);
            $ref = new \ReflectionProperty($translator, 'loader');
            $ref->setValue($translator, $loader);
            $translator->setFallback('pt-PT');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $locale = $this->resolveLocale();
        app()->setLocale($locale);

        if ($this->app->bound('translator')) {
            $translator = $this->app->make('translator');
            $translator->setLocale($locale);
            $translator->setFallback('pt-PT');
        }
    }

    private function resolveLocale(): string
    {
        // Authenticated user preference takes priority
        if (Auth::check() && Auth::user()->preferred_language_code) {
            return Auth::user()->preferred_language_code;
        }

        // Session-stored locale for pre-login selection
        if (session()->has('locale')) {
            return session('locale');
        }

        return config('app.locale', 'pt-PT');
    }
}
