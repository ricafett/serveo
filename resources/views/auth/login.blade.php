<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ __('auth.sign_in') }} — {{ config('app.name', 'Serveo') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <script>
        // Apply theme immediately to prevent flash
        (function() {
            const theme = localStorage.getItem('theme') || 'system';
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
        });
    </script>
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-900 dark:bg-gray-950 dark:text-gray-100">
    {{-- Top bar with language + theme (consistent with operational layout) --}}
    <header class="fixed top-0 left-0 right-0 z-30 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-6">
            <div class="flex items-center justify-end h-14 gap-2">
                <livewire:language-switcher />
                <livewire:theme-toggle />
            </div>
        </div>
    </header>

    <div class="min-h-screen flex items-center justify-center p-4 pt-20">
        <div class="w-full max-w-sm">
            {{-- Logo --}}
            <div class="flex flex-col items-center mb-8">
                <svg class="h-12 w-12 text-primary-600 dark:text-primary-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                </svg>
                <h1 class="text-2xl font-bold">{{ config('app.name', 'Serveo') }}</h1>
            </div>

            {{-- Login Form --}}
            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                @if($errors->any())
                    <div class="rounded-lg bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-600 dark:text-red-400">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('auth.username') }}</label>
                    <input
                        id="username"
                        name="username"
                        type="text"
                        required
                        autofocus
                        autocomplete="username"
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 px-3"
                        placeholder="{{ __('auth.enter_username') }}"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('auth.password') }}</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 px-3"
                        placeholder="{{ __('auth.enter_password') }}"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full flex justify-center items-center rounded-lg bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 min-h-[48px] transition-colors"
                >
                    {{ __('auth.sign_in') }}
                </button>
            </form>
        </div>
    </div>

    @livewireScripts
</body>
</html>
