<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ThemeToggle extends Component
{
    public string $theme = User::THEME_SYSTEM;

    public function mount(): void
    {
        $this->theme = Auth::user()?->theme ?? User::THEME_SYSTEM;
    }

    public function setTheme(string $theme): void
    {
        if (! in_array($theme, [User::THEME_LIGHT, User::THEME_DARK, User::THEME_SYSTEM], true)) {
            return;
        }

        $this->theme = $theme;

        if ($user = Auth::user()) {
            $user->update(['theme' => $theme]);
        }

        // Dispatch a browser event so any inline script can react immediately.
        $this->dispatch('theme-changed', theme: $theme);
    }

    public function render()
    {
        return view('livewire.theme-toggle');
    }
}
