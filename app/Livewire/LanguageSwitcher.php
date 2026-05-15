<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class LanguageSwitcher extends Component
{
    public string $locale = 'pt-PT';

    public function mount(): void
    {
        $this->locale = app()->getLocale();
    }

    public function setLocale(string $locale): void
    {
        if (! in_array($locale, ['pt-PT', 'en-US'], true)) {
            return;
        }

        $this->locale = $locale;
        session()->put('locale', $locale);

        if ($user = Auth::user()) {
            $user->update(['preferred_language_code' => $locale]);
        }

        // Clear translation cache so new locale is loaded immediately.
        // NOTE: Cache::flush() is required because the array cache driver
        // (used in tests and local dev) does not support tag-based eviction.
        // In production with Redis/Memcached, this should be replaced with
        // tagged cache invalidation on the 'translations' tag.
        \Illuminate\Support\Facades\Cache::flush();

        $this->redirect(request()->fullUrl());
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}
