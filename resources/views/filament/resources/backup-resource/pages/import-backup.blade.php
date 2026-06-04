<x-filament-panels::page>
    <div class="mx-auto max-w-2xl">
        <div class="rounded-lg border bg-white p-6 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold">{{ __('app.backup_import') }}</h2>

            <div class="mb-6 rounded-lg border border-danger-200 bg-danger-50 p-4 dark:border-danger-800 dark:bg-danger-950">
                <p class="text-sm text-danger-700 dark:text-danger-300">
                    <strong>{{ __('app.warning') }}:</strong> {{ __('app.backup_import_warning') }}
                </p>
            </div>

            <form wire:submit="submit">
                {{ $this->form }}

                <div class="mt-6">
                    <button
                        type="submit"
                        class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:border-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500 fi-btn-color-warning fi-ac-btn fi-ac-btn-action fi-btn-size-md inline-grid rounded-lg px-3 py-2 text-sm shadow-sm fi-btn fi-ac"
                    >
                        {{ __('app.backup_restore_start') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>
