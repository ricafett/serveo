<div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-6 py-6">
    {{-- Active Session Banner --}}
    <div class="mb-6">
        @if ($activeSession)
            <div class="flex items-center gap-2 rounded-lg bg-primary-50 dark:bg-primary-900/20 px-4 py-3 text-base font-medium text-primary-700 dark:text-primary-300">
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ __('dashboard.active_session') }}: {{ $activeSession->session_label }}</span>
            </div>
        @else
            <div class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20 px-4 py-4 text-base">
                <svg class="h-5 w-5 shrink-0 text-red-600 dark:text-red-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div class="text-red-700 dark:text-red-300">
                    <p class="font-semibold">{{ __('dashboard.no_session') }}</p>
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ __('dashboard.no_session_help') }}</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Navigation Tiles --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($tiles as $tile)
            <a
                href="{{ route($tile['route']) }}"
                class="group flex flex-col items-start gap-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-sm transition-all hover:shadow-md hover:border-gray-300 dark:hover:border-gray-700 active:scale-[0.98] min-h-[140px]"
            >
                <div class="flex items-center gap-3 w-full">
                    <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl {{ $tile['color'] }}">
                        {!! $tile['icon'] !!}
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $tile['label'] }}</h2>
                        <p class="mt-0.5 text-base text-gray-500 dark:text-gray-400">{{ $tile['description'] }}</p>
                    </div>
                    <svg class="h-5 w-5 shrink-0 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                    </svg>
                </div>
            </a>
        @endforeach
    </div>
</div>
