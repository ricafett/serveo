<div class="flex items-center gap-1">
    <button
        type="button"
        wire:click="setLocale('pt-PT')"
        title="Português"
        class="rounded px-2 py-1 text-xs font-semibold transition {{ $locale === 'pt-PT' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' }}"
    >
        PT
    </button>
    <button
        type="button"
        wire:click="setLocale('en-US')"
        title="English"
        class="rounded px-2 py-1 text-xs font-semibold transition {{ $locale === 'en-US' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300' : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800' }}"
    >
        EN
    </button>
</div>
