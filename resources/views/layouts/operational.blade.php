<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $themeClass ?? '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', 'Serveo'))</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <script>
        // Apply theme immediately to prevent flash
        (function() {
            const theme = localStorage.getItem('theme') || '{{ auth()->user()?->theme ?? 'system' }}';
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = theme === 'dark' || (theme === 'system' && systemDark);
            if (isDark) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();

        // Listen for theme changes from Livewire toggle so the DOM updates immediately
        document.addEventListener('livewire:init', () => {
            Livewire.on('theme-changed', (event) => {
                const theme = event.theme;
                localStorage.setItem('theme', theme);
                const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = theme === 'dark' || (theme === 'system' && systemDark);
                if (isDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            });

            // Re-apply theme after every wire:navigate SPA navigation.
            // Livewire replaces the DOM during navigate, which can wipe
            // the `dark` class set by the IIFE above.
            document.addEventListener('livewire:navigated', () => {
                const theme = localStorage.getItem('theme') || '{{ auth()->user()?->theme ?? 'system' }}';
                const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = theme === 'dark' || (theme === 'system' && systemDark);
                if (isDark) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            });
        });
    </script>
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
    <div class="min-h-screen flex flex-col">
        {{-- Top Bar --}}
        <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-6">
                <div class="flex items-center justify-between h-14">
                    {{-- Logo / Brand --}}
                    <a href="{{ route('home') }}" class="flex items-center gap-2 shrink-0">
                        <svg class="h-7 w-7 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                        <span class="font-bold text-lg tracking-tight hidden sm:inline">{{ config('app.name', 'Serveo') }}</span>
                    </a>

                    {{-- Center: Context title (slot) --}}
                    <div class="flex-1 min-w-0 px-2 sm:px-4">
                        @yield('header-title')
                    </div>

                    {{-- Right: User dropdown with theme and language inside --}}
                    <div class="relative shrink-0" x-data="{ open: false }" @click.away="open = false">
                        <button
                            @click="open = !open"
                            type="button"
                            class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 min-h-[44px]"
                            aria-label="{{ __('auth.user_menu') }}"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                            <span class="hidden md:inline max-w-[120px] truncate">{{ auth()->user()?->name }}</span>
                            <svg class="h-4 w-4 shrink-0 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>

                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute right-0 mt-2 w-56 rounded-lg bg-white dark:bg-gray-900 shadow-lg ring-1 ring-black/5 dark:ring-white/10 py-1 z-50 origin-top-right"
                            style="display: none;"
                        >
                            {{-- User info --}}
                            <div class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800">
                                <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ auth()->user()?->name }}</div>
                                <div class="truncate">{{ auth()->user()?->email }}</div>
                                <div class="mt-1 capitalize">{{ auth()->user()?->roles?->first()?->name }}</div>
                            </div>

                            {{-- Theme --}}
                            <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-800">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5 uppercase tracking-wider">{{ __('app.theme') }}</div>
                                <livewire:theme-toggle />
                            </div>

                            {{-- Language --}}
                            <div class="px-3 py-2 border-b border-gray-100 dark:border-gray-800">
                                <div class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5 uppercase tracking-wider">{{ __('app.language') }}</div>
                                <livewire:language-switcher :inline="true" />
                            </div>

                            @if(auth()->user()?->hasRole('ADMIN'))
                                <a href="{{ route('filament.admin.pages.dashboard') }}" class="block px-3 py-2.5 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 min-h-[44px] flex items-center border-b border-gray-100 dark:border-gray-800">
                                    {{ __('auth.admin_panel') }}
                                </a>
                            @endif

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full text-left px-3 py-2.5 text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20 min-h-[44px] flex items-center">
                                    {{ __('auth.log_out') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Main Content --}}
        <main class="flex-1 overflow-y-auto pb-20 sm:pb-6">
            {{ $slot ?? '' }}
        </main>

        {{-- Bottom Navigation (Mobile-First) --}}
        @include('components.operational.nav')
    </div>

    @livewireScripts
</body>
</html>
