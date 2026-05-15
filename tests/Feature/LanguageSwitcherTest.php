<?php

use App\Livewire\LanguageSwitcher;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    $this->session = bootScenario();
    $this->user = makeUser('SERVER');
});

it('sets session locale when user clicks locale button', function () {
    Livewire::actingAs($this->user)
        ->test(LanguageSwitcher::class)
        ->call('setLocale', 'en-US')
        ->assertRedirect();

    expect(session('locale'))->toBe('en-US');
});

it('updates user preferred_language_code', function () {
    Livewire::actingAs($this->user)
        ->test(LanguageSwitcher::class)
        ->call('setLocale', 'en-US')
        ->assertRedirect();

    expect($this->user->fresh()->preferred_language_code)->toBe('en-US');
});

it('rejects invalid locale values', function () {
    Livewire::actingAs($this->user)
        ->test(LanguageSwitcher::class)
        ->call('setLocale', 'fr-FR')
        ->assertNoRedirect();

    expect(session('locale'))->not->toBe('fr-FR');
    expect($this->user->fresh()->preferred_language_code)->not->toBe('fr-FR');
});

it('sets locale from user preference on mount', function () {
    $this->user->update(['preferred_language_code' => 'en-US']);

    $component = Livewire::actingAs($this->user)
        ->test(LanguageSwitcher::class);

    expect($component->get('locale'))->toBe('en-US');
});

it('sets locale from session when no auth user', function () {
    session()->put('locale', 'en-US');

    $component = Livewire::test(LanguageSwitcher::class);

    expect($component->get('locale'))->toBe('en-US');
});

it('defaults to app locale when no session and no auth user', function () {
    $component = Livewire::test(LanguageSwitcher::class);

    expect($component->get('locale'))->toBe(config('app.locale', 'pt-PT'));
});
