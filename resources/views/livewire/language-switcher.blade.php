@if($inline)
    {{-- Inline mode: flat buttons matching theme-toggle styling --}}
    <div class="flex items-center gap-2">
        <button
            type="button"
            wire:click="setLocale('pt-PT')"
            title="Português"
            dusk="switch-locale-pt-PT"
            class="rounded px-2 py-1 text-base font-semibold transition {{ $locale === 'pt-PT' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' }}"
        >
            PT
        </button>
        <button
            type="button"
            wire:click="setLocale('en-US')"
            title="English"
            dusk="switch-locale-en-US"
            class="rounded px-2 py-1 text-base font-semibold transition {{ $locale === 'en-US' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' }}"
        >
            EN
        </button>
    </div>
@else
    {{-- Dropdown mode: compact trigger with overlay (admin panel, etc.) --}}
    <div class="relative inline-block" x-data="{ open: false }" @click.away="open = false">
        <button
            type="button"
            @click="open = !open"
            class="flex items-center justify-center gap-1.5 h-9 rounded-md text-base font-medium transition-colors
                   bg-gray-100 text-gray-700 hover:bg-gray-200
                   dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700
                   px-2 sm:px-2.5"
            aria-label="{{ __('app.select') }} language"
        >
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A11.959 11.959 0 013.72 9.75m0 0A8.959 8.959 0 013 12c0 .778.099 1.533.284 2.253m0 0A11.959 11.959 0 0120.28 9.75" />
            </svg>
            <span class="hidden sm:inline text-sm font-semibold uppercase tracking-wide">{{ $locale === 'pt-PT' ? 'PT' : 'EN' }}</span>
            <svg class="h-3 w-3 opacity-60 hidden sm:inline shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="absolute right-0 mt-1.5 w-44 rounded-lg bg-white dark:bg-gray-800 shadow-xl ring-1 ring-black/5 dark:ring-white/10 z-[100] overflow-hidden origin-top-right"
            style="display: none;"
        >
            <button
                type="button"
                wire:click="setLocale('pt-PT')"
                @click="open = false"
                dusk="switch-locale-pt-PT"
                class="w-full text-left px-3 py-2 text-base flex items-center gap-2.5 transition-colors
                    {{ $locale === 'pt-PT' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300 font-medium' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50' }}"
            >
                <span class="text-base leading-none">🇵🇹</span>
                <span class="flex-1">Português</span>
                @if($locale === 'pt-PT')
                    <span class="h-2 w-2 rounded-full bg-primary-500 shrink-0"></span>
                @endif
            </button>
            <button
                type="button"
                wire:click="setLocale('en-US')"
                @click="open = false"
                dusk="switch-locale-en-US"
                class="w-full text-left px-3 py-2 text-base flex items-center gap-2.5 transition-colors
                    {{ $locale === 'en-US' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/20 dark:text-primary-300 font-medium' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700/50' }}"
            >
                <span class="text-base leading-none">🇺🇸</span>
                <span class="flex-1">English</span>
                @if($locale === 'en-US')
                    <span class="h-2 w-2 rounded-full bg-primary-500 shrink-0"></span>
                @endif
            </button>
        </div>
    </div>
@endif
