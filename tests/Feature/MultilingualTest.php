<?php

use App\Models\TranslationKey;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    bootScenario();
});

it('loads translations from database via custom loader', function () {
    TranslationKey::create([
        'language_code' => 'en-US',
        'translation_namespace' => 'floor',
        'translation_key' => 'title',
        'translation_value' => 'Floor map',
        'is_active' => true,
    ]);

    app()->setLocale('en-US');
    Cache::flush();
    app()->forgetInstance('translator');

    expect(__('floor.title'))->toBe('Floor map');
});

it('falls back to default locale when key is missing', function () {
    TranslationKey::create([
        'language_code' => 'en',
        'translation_namespace' => 'floor',
        'translation_key' => 'title',
        'translation_value' => 'Floor map (fallback)',
        'is_active' => true,
    ]);

    app()->setLocale('en-US');
    Cache::flush();
    app()->forgetInstance('translator');

    // en is the default fallback locale
    expect(__('floor.title'))->toBe('Floor map (fallback)');
});

it('does not expose raw key when translation is missing in current locale', function () {
    // Create translation only in fallback locale (en)
    TranslationKey::create([
        'language_code' => 'en',
        'translation_namespace' => 'order',
        'translation_key' => 'submit',
        'translation_value' => 'Submit Order (fallback)',
        'is_active' => true,
    ]);

    app()->setLocale('en-US');
    Cache::flush();
    app()->forgetInstance('translator');

    // Should fall back to en instead of exposing raw key
    $result = __('order.submit');
    expect($result)->toBe('Submit Order (fallback)')
        ->and($result)->not->toBe('order.submit');
});

it('updates ui when locale is switched', function () {
    TranslationKey::create([
        'language_code' => 'en-US',
        'translation_namespace' => 'order',
        'translation_key' => 'title',
        'translation_value' => 'Order',
        'is_active' => true,
    ]);

    TranslationKey::create([
        'language_code' => 'pt-PT',
        'translation_namespace' => 'order',
        'translation_key' => 'title',
        'translation_value' => 'Pedido',
        'is_active' => true,
    ]);

    app()->setLocale('pt-PT');
    Cache::flush();
    app()->forgetInstance('translator');
    expect(__('order.title'))->toBe('Pedido');

    app()->setLocale('en-US');
    Cache::flush();
    app()->forgetInstance('translator');
    expect(__('order.title'))->toBe('Order');
});

it('caches database translations', function () {
    TranslationKey::create([
        'language_code' => 'en-US',
        'translation_namespace' => 'app',
        'translation_key' => 'name',
        'translation_value' => 'Cached Serveo',
        'is_active' => true,
    ]);

    app()->setLocale('en-US');
    Cache::flush();
    app()->forgetInstance('translator');

    // First load should cache
    __('app.name');

    // Delete the record
    TranslationKey::where('translation_key', 'app.name')->delete();

    // Should still return cached value
    expect(__('app.name'))->toBe('Cached Serveo');
});
