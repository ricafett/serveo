<?php

use App\Models\TranslationKey;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    bootScenario();
});

it('loads translations from database via custom loader', function () {
    TranslationKey::create([
        'language_code' => 'en-US',
        'translation_namespace' => '*',
        'translation_key' => 'floor.title',
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
        'language_code' => 'pt-PT',
        'translation_namespace' => '*',
        'translation_key' => 'floor.title',
        'translation_value' => 'Plano de sala',
        'is_active' => true,
    ]);

    app()->setLocale('en-US');
    Cache::flush();
    app()->forgetInstance('translator');

    // pt-PT is the default fallback locale
    expect(__('floor.title'))->toBe('Plano de sala');
});

it('does not expose raw key when translation is missing in current locale', function () {
    // Create translation only in fallback locale (pt-PT)
    TranslationKey::create([
        'language_code' => 'pt-PT',
        'translation_namespace' => '*',
        'translation_key' => 'order.submit',
        'translation_value' => 'Enviar pedido',
        'is_active' => true,
    ]);

    app()->setLocale('en-US');
    Cache::flush();
    app()->forgetInstance('translator');

    // Should fall back to pt-PT instead of exposing raw key
    $result = __('order.submit');
    expect($result)->toBe('Enviar pedido')
        ->and($result)->not->toBe('order.submit');
});

it('updates ui when locale is switched', function () {
    TranslationKey::create([
        'language_code' => 'en-US',
        'translation_namespace' => '*',
        'translation_key' => 'order.title',
        'translation_value' => 'Order',
        'is_active' => true,
    ]);

    TranslationKey::create([
        'language_code' => 'pt-PT',
        'translation_namespace' => '*',
        'translation_key' => 'order.title',
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
        'translation_namespace' => '*',
        'translation_key' => 'app.name',
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
