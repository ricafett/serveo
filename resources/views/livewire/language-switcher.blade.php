<div class="relative" x-data="{ open: false }" @click.away="open = false">
    <button
        type="button"
        @click="open = !open"
        class="flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-sm font-medium transition-colors min-h-[44px] min-w-[44px] justify-center
            {{ $locale === 'pt-PT'
                ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300'
                : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}"
        aria-label="{{ __('app.select') }} language"
    >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A11.959 11.959 0 013.72 9.75m0 0A8.959 8.959 0 013 12c0 .778.099 1.533.284 2.253m0 0A11.959 11.959 0 0120.28 9.75" />
        </svg>
        <span class="hidden sm:inline text-xs font-semibold uppercase">{{ str_replace('-', '_', $locale) === 'pt_PT' ? 'PT' : 'EN' }}</span>
        <svg class="h-3.5 w-3.5 opacity-60 hidden sm:inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
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
        class="absolute right-0 mt-2 w-40 rounded-lg bg-white dark:bg-gray-900 shadow-lg ring-1 ring-black/5 dark:ring-white/10 py-1 z-50 origin-top-right"
        style="display: none;"
    >
        <button
            type="button"
            wire:click="setLocale('pt-PT')"
            @click="open = false"
            dusk="switch-locale-pt-PT"
            class="w-full text-left px-3 py-2.5 text-sm flex items-center gap-2 transition-colors min-h-[44px]
                {{ $locale === 'pt-PT' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 font-semibold' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}"
        >
            <span class="text-base leading-none">🇵🇹</span>
            <span>Português</span>
            @if($locale === 'pt-PT')
                <svg class="ml-auto h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            @endif
        </button>
        <button
            type="button"
            wire:click="setLocale('en-US')"
            @click="open = false"
            dusk="switch-locale-en-US"
            class="w-full text-left px-3 py-2.5 text-sm flex items-center gap-2 transition-colors min-h-[44px]
                {{ $locale === 'en-US' ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 font-semibold' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}"
        >
            <span class="text-base leading-none">🇺🇸</span>
            <span>English</span>
            @if($locale === 'en-US')
                <svg class="ml-auto h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            @endif
        </button>
    </div>
</div>
