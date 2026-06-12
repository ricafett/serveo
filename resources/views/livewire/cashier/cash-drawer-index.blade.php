<div class="p-4 sm:p-6 lg:p-8">
    <div class="max-w-2xl mx-auto">
        {{-- Session context --}}
        @if($session)
            <div class="mb-4 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ $session->session_label }}</span>
                <span class="mx-1">·</span>
                <span class="rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 text-xs font-medium">{{ __('app.status_open') }}</span>
            </div>
        @endif

        {{-- Error / Success messages --}}
        @if($errorMessage)
            <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 p-3 text-base text-red-600 dark:text-red-400">{{ $errorMessage }}</div>
        @endif
        @if($successMessage)
            <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-base text-green-600 dark:text-green-400">{{ $successMessage }}</div>
        @endif

        {{-- No session state --}}
        @if(! $session || ! $session->isOpen())
            <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400 dark:text-gray-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M6.75 3.75h10.5a2.25 2.25 0 012.25 2.25v12a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 18V6a2.25 2.25 0 012.25-2.25zM7.5 12h9m-9 3h6" />
                </svg>
                <p class="text-lg font-medium text-gray-700 dark:text-gray-300">{{ __('cashdrawer.title') }}</p>
                <p class="mt-1 text-base text-gray-500 dark:text-gray-400">{{ __('cashdrawer.no_session') }}</p>
            </div>
        @else
            {{-- Balance Card --}}
            <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden mb-4">
                <div class="px-5 py-4">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('cashdrawer.current_balance') }}</div>
                    <div class="mt-1 text-4xl font-bold {{ $balance >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ number_format($balance, 2) }} €
                    </div>
                </div>
                <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('cashdrawer.session_balance') }}</span>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="openDrawer"
                            wire:target="openDrawer"
                            wire:loading.attr="disabled"
                            class="rounded-lg p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 min-h-[44px] min-w-[44px] flex items-center justify-center"
                            title="{{ __('cashdrawer.open_drawer') }}"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            wire:click="printTotals"
                            wire:target="printTotals"
                            wire:loading.attr="disabled"
                            class="rounded-lg p-2 text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 min-h-[44px] min-w-[44px] flex items-center justify-center"
                            title="{{ __('cashdrawer.print_totals') }}"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
                            </svg>
                        </button>
                        <button
                        type="button"
                        wire:click="$toggle('showForm')"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 min-h-[44px] transition-colors"
                    >
                        @if($showForm)
                            {{ __('app.cancel') }}
                        @else
                            {{ __('cashdrawer.record_movement') }}
                        @endif
                    </button>
                </div>
            </div>

            {{-- Movement Form --}}
            @if($showForm)
                <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-5 mb-4 space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('cashdrawer.new_movement') }}</h2>

                    {{-- Type selector --}}
                    <div>
                        <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('cashdrawer.movement_type') }}</label>
                        <div class="flex rounded-lg bg-gray-100 dark:bg-gray-800 p-1">
                            <button type="button" wire:click="$set('movementType', 'CASH_IN')"
                                class="flex-1 rounded-md px-4 py-2.5 text-base font-medium min-h-[44px] transition-colors {{ $movementType === 'CASH_IN' ? 'bg-emerald-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-400' }}">
                                {{ __('cashdrawer.cash_in') }}
                            </button>
                            <button type="button" wire:click="$set('movementType', 'CASH_OUT')"
                                class="flex-1 rounded-md px-4 py-2.5 text-base font-medium min-h-[44px] transition-colors {{ $movementType === 'CASH_OUT' ? 'bg-red-500 text-white shadow-sm' : 'text-gray-600 dark:text-gray-400' }}">
                                {{ __('cashdrawer.cash_out') }}
                            </button>
                        </div>
                    </div>

                    {{-- Amount --}}
                    <div>
                        <label for="cashdrawer-amount" class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('cashdrawer.amount') }}</label>
                        <div class="relative">
                            <input id="cashdrawer-amount" type="number" wire:model="movementAmount" step="0.01" min="0.01"
                                class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base h-11 px-3 pr-12">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 text-base">€</span>
                        </div>
                    </div>

                    {{-- Label --}}
                    <div>
                        <label for="cashdrawer-label" class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('cashdrawer.label') }}</label>
                        <input id="cashdrawer-label" type="text" wire:model="movementLabel"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base h-11 px-3"
                            placeholder="{{ $movementType === 'CASH_IN' ? __('cashdrawer.cash_in_examples') : __('cashdrawer.cash_out_examples') }}">
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label for="cashdrawer-notes" class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('app.notes') }}</label>
                        <textarea id="cashdrawer-notes" wire:model="movementNotes" rows="2"
                            class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base p-3"></textarea>
                    </div>

                    {{-- Current balance hint --}}
                    @if($movementType === 'CASH_OUT')
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('cashdrawer.available_balance') }}: {{ number_format($balance, 2) }} €
                        </div>
                    @endif

                    {{-- Submit --}}
                    <button
                        type="button"
                        wire:click="recordMovement"
                        wire:target="recordMovement"
                        wire:loading.attr="disabled"
                        class="w-full flex justify-center rounded-lg {{ $movementType === 'CASH_IN' ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-red-600 hover:bg-red-500' }} px-4 py-3 text-base font-semibold text-white shadow-sm min-h-[48px] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        @if($movementType === 'CASH_IN')
                            {{ __('cashdrawer.record_cash_in') }}
                        @else
                            {{ __('cashdrawer.record_cash_out') }}
                        @endif
                    </button>
                </div>
            @endif

            {{-- Timeline --}}
            <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-white">{{ __('cashdrawer.history') }}</h2>
                    <button
                        type="button"
                        wire:click="refreshData"
                        class="text-sm text-primary-600 dark:text-primary-400 hover:underline"
                    >
                        {{ __('cashier.refresh') }}
                    </button>
                </div>

                @if(count($timeline) > 0)
                    <div class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach($timeline as $item)
                            <div class="px-5 py-3 flex items-center gap-3">
                                {{-- Icon --}}
                                <div class="shrink-0">
                                    @if($item['type'] === 'CASH_IN')
                                        <div class="w-9 h-9 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                            <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m0 0l6.75-6.75M12 19.5l-6.75-6.75" />
                                            </svg>
                                        </div>
                                    @elseif($item['type'] === 'CASH_OUT')
                                        <div class="w-9 h-9 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                                            <svg class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5v-15m0 0l-6.75 6.75M12 4.5l6.75 6.75" />
                                            </svg>
                                        </div>
                                    @elseif($item['type'] === 'payment_billing')
                                        <div class="w-9 h-9 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                            <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15a2.25 2.25 0 012.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                                            </svg>
                                        </div>
                                    @elseif($item['type'] === 'payment_sale')
                                        <div class="w-9 h-9 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                                            <svg class="h-5 w-5 text-purple-600 dark:text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M6.75 3.75h10.5a2.25 2.25 0 012.25 2.25v12a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 18V6a2.25 2.25 0 012.25-2.25zM7.5 12h9m-9 3h6" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>

                                {{-- Content --}}
                                <div class="flex-1 min-w-0">
                                    <div class="text-base font-medium text-gray-900 dark:text-white truncate">
                                        {{ $item['label'] }}
                                        @if($item['source'])
                                            <span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-1">({{ $item['source'] }})</span>
                                        @endif
                                    </div>
                                    @if($item['notes'])
                                        <div class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ $item['notes'] }}</div>
                                    @endif
                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ \Illuminate\Support\Carbon::parse($item['recorded_at'])->format('d/m/Y H:i') }}
                                    </div>
                                </div>

                                {{-- Amount --}}
                                <div class="shrink-0 text-right">
                                    @if(in_array($item['type'], ['CASH_IN', 'payment_billing', 'payment_sale']))
                                        <span class="text-base font-semibold text-emerald-600 dark:text-emerald-400">+{{ number_format($item['amount'], 2) }} €</span>
                                    @elseif($item['type'] === 'CASH_OUT')
                                        <span class="text-base font-semibold text-red-600 dark:text-red-400">-{{ number_format($item['amount'], 2) }} €</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-5 py-8 text-center text-base text-gray-500 dark:text-gray-400">
                        {{ __('cashdrawer.no_movements') }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
