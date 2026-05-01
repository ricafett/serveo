<div class="p-4 sm:p-6 lg:p-8">
    <div class="max-w-4xl mx-auto">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Checkout') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Billing Group') }} #{{ $id }}</p>
            </div>
            <a href="{{ route('lookup') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800 min-h-[44px] flex items-center">
                {{ __('Back') }}
            </a>
        </div>

        <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-8 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Checkout will be implemented in issue #21. This is the operational layout shell.') }}
            </p>
        </div>
    </div>
</div>
