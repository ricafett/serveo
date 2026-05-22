<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class LanguageSwitcher extends Component
{
    public string $locale = 'pt-PT';

    public bool $inline = false;

    public function mount(bool $inline = false): void
    {
        $this->inline = $inline;

        $locale = Auth::check()
            ? (Auth::user()->preferred_language_code ?? session('locale'))
            : session('locale');

        $this->locale = $locale ?? config('app.locale');
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
        Cache::flush();

        // Use a JavaScript reload to avoid request()->fullUrl() returning
        // the Livewire update endpoint during component updates.
        $this->js('window.location.reload()');
    }

    public function render()
    {
        return view('livewire.language-switcher');
    }
}
