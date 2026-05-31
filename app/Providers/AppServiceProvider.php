<?php

namespace App\Providers;

use App\Domain\Localization\DatabaseTranslationLoader;
use App\Models\AuditEvent;
use App\Models\BillingDocument;
use App\Models\BillingGroup;
use App\Models\FulfillmentRoute;
use App\Models\OrderHeader;
use App\Models\PaymentRecord;
use App\Models\Printer;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Observers\FulfillmentRouteObserver;
use App\Policies\AuditEventPolicy;
use App\Policies\BillingDocumentPolicy;
use App\Policies\BillingGroupPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PaymentRecordPolicy;
use App\Policies\PrinterPolicy;
use App\Policies\SalePaymentPolicy;
use App\Policies\SalePolicy;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
            $translator->setFallback(config('app.fallback_locale', 'pt-PT'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
        Gate::policy(BillingDocument::class, BillingDocumentPolicy::class);
        Gate::policy(BillingGroup::class, BillingGroupPolicy::class);
        Gate::policy(OrderHeader::class, OrderPolicy::class);
        Gate::policy(PaymentRecord::class, PaymentRecordPolicy::class);
        Gate::policy(Printer::class, PrinterPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(SalePayment::class, SalePaymentPolicy::class);

        FulfillmentRoute::observe(FulfillmentRouteObserver::class);

        $locale = $this->resolveLocale();
        app()->setLocale($locale);

        if ($this->app->bound('translator')) {
            $translator = $this->app->make('translator');
            $translator->setLocale($locale);
            $translator->setFallback(config('app.fallback_locale', 'pt-PT'));
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
