<div class="p-4 sm:p-6 lg:p-8">
    <div class="max-w-7xl mx-auto">
        {{-- No Session Warning --}}
        @if(! $this->hasOpenSession())
            <div class="mb-6 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20 px-4 py-4 text-sm">
                <svg class="h-5 w-5 shrink-0 text-red-600 dark:text-red-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                <div class="text-red-700 dark:text-red-300">
                    <p class="font-semibold">{{ __('dashboard.no_session') }}</p>
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ __('dashboard.no_session_help') }}</p>
                </div>
            </div>
        @endif

        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('cashier.billing_groups') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('cashier.search_placeholder') }}</p>
            </div>
        </div>

        {{-- Search & Filter --}}
        <div class="mb-6 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                </svg>
                <input
                    id="search"
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="{{ __('cashier.search_placeholder') }}"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 pl-10 pr-3"
                >
            </div>
            <label class="inline-flex items-center gap-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer min-h-[44px]">
                <input id="show-closed" type="checkbox" wire:model.live="showClosed" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                {{ __('cashier.show_closed') }}
            </label>
        </div>

        {{-- Groups List --}}
        @if($this->groups->count() > 0)
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($this->groups as $group)
                    <button
                        type="button"
                        wire:click="openCheckout({{ $group->id }})"
                        class="block rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 text-left hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $group->longLabel() }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $group->status?->code === 'ACTIVE' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                {{ $group->status?->code === 'WAITING' ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400' : '' }}
                                {{ $group->status?->code === 'CHECK_REQUESTED' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                                {{ $group->status?->code === 'PARTIALLY_PAID' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : '' }}
                                {{ $group->is_closed ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : '' }}
                            ">
                                {{ $group->status?->display_name ?? $group->status?->code }}
                            </span>
                        </div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            @foreach($group->occupiedZones as $zone)
                                <div>{{ $zone->row?->section?->section_code }} · {{ $zone->row?->row_code }} · {{ $zone->rangeLabel() }}</div>
                            @endforeach
                        </div>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $group->cover_count ? $group->cover_count . ' ' . __('app.covers') : '' }}</span>
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ number_format($group->balance(), 2) }}</span>
                        </div>
                    </button>
                @endforeach
            </div>
        @else
            <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-8 text-center">
                <p class="text-gray-500 dark:text-gray-400">{{ __('cashier.no_groups_found') }}</p>
            </div>
        @endif
    </div>
</div>
