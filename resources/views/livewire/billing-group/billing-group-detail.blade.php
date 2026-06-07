<div class="p-4 sm:p-6 lg:p-8"
    x-data="{
        addZoneModal: @entangle('showAddZoneModal'),
        releaseModal: @entangle('showReleaseModal'),
        voidModal: @entangle('showVoidModal'),
    }"
    wire:poll.10s="refreshData">
    <div class="max-w-4xl mx-auto">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    onclick="window.history.back()"
                    class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $group?->longLabel() }}</h1>
                    <p class="text-base text-gray-500 dark:text-gray-400">
                        {{ $group?->display_code }} · {{ $group?->status?->display_name ?? $group?->status?->code }}
                        @if($group?->is_closed)
                            <span class="ml-2 inline-flex items-center rounded-full px-2 py-0.5 text-sm font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('app.closed') }}</span>
                        @endif
                    </p>
                </div>
            </div>

            {{-- Favorite toggle --}}
            @php
                $isFavorited = $this->isFavorited;
                $favPivot = $group?->favoritedBy?->where('id', Auth::id())->first();
                $isAutoFavorite = $isFavorited && ($favPivot?->pivot?->is_manual === false);
            @endphp
            <button
                type="button"
                wire:click="toggleFavorite"
                class="flex items-center justify-center min-h-[64px] min-w-[64px] rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                title="{{ $isAutoFavorite ? __('floor.auto_favorite') : ($isFavorited ? __('floor.unfavorite') : __('floor.favorite')) }}"
            >
                @if($isFavorited)
                    <svg class="w-10 h-10 text-yellow-500" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                @else
                    <svg class="w-10 h-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                @endif
            </button>
        </div>

        {{-- Banner Alerts --}}
        @if($errorMessage)
            <div class="mb-6 rounded-lg bg-red-50 dark:bg-red-900/20 p-4 text-base text-red-600 dark:text-red-400">{{ $errorMessage }}</div>
        @endif
        @if($successMessage)
            <div class="mb-6 rounded-lg bg-green-50 dark:bg-green-900/20 p-4 text-base text-green-600 dark:text-green-400">{{ $successMessage }}</div>
        @endif

        {{-- Actions Bar --}}
        <div class="mb-6 flex flex-wrap gap-2">
            @if(! $group?->is_closed)
                @can('order.create')
                    <button type="button" wire:click="addOrder" class="rounded-lg bg-primary-600 px-4 py-2.5 text-base font-semibold text-white hover:bg-primary-500 min-h-[44px] flex items-center gap-1.5 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        {{ __('billing.add_order') }}
                    </button>
                @endcan
                @can('floor.assign_zone')
                    <button type="button" @click="addZoneModal = true; $wire.openAddZoneModal()" class="rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 min-h-[44px] flex items-center gap-1.5 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        {{ __('billing.add_zone') }}
                    </button>
                @endcan
                @can('billing_document.create')
                    <button type="button" wire:click="printBill" wire:target="printBill" wire:loading.attr="disabled" class="rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 min-h-[44px] flex items-center gap-1.5 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" /></svg>
                        {{ __('billing.print_bill') }}
                    </button>
                @endcan
                @can('payment.record')
                    <a href="#payment" class="rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 min-h-[44px] flex items-center gap-1.5 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" /></svg>
                        {{ __('cashier.record_payment') }}
                    </a>
                @endcan
                @can('billing_group.set_status')
                    <button type="button" wire:click="closeGroup" wire:target="closeGroup" wire:loading.attr="disabled" class="rounded-lg bg-white dark:bg-gray-800 border border-red-300 dark:border-red-800 px-4 py-2.5 text-base font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 min-h-[44px] flex items-center gap-1.5 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                        {{ __('cashier.close_group') }}
                    </button>
                @endcan
            @else
                @can('billing_group.reopen')
                    <button type="button" wire:click="reopenGroup" wire:target="reopenGroup" wire:loading.attr="disabled" class="rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 min-h-[44px] flex items-center gap-1.5 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                        {{ __('cashier.reopen') }}
                    </button>
                @endcan
            @endif
            @can('billing_document.reprint')
                @if($group?->billingDocuments?->count() > 0)
                    <a href="{{ route('reprint.group', ['billingGroupId' => $group->id]) }}" wire:navigate class="rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 min-h-[44px] flex items-center gap-1.5 transition-colors">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" /></svg>
                        {{ __('cashier.reprint') }}
                    </a>
                @endif
            @endcan
        </div>

        {{-- Zones --}}
        <div class="mb-6 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900 dark:text-white">{{ __('billing.occupied_zones') }}</h2>
                <span class="text-base text-gray-500 dark:text-gray-400">{{ $group?->occupiedZones?->count() ?? 0 }}</span>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse($group?->occupiedZones ?? [] as $zone)
                    <div class="px-4 py-3 flex items-center justify-between">
                        <div>
                            <div class="text-base font-medium text-gray-900 dark:text-white">{{ $zone->rangeLabelWithCount() }}</div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $zone->defaultDeliveryLabel() }}
                                @if($zone->server)
                                    · {{ $zone->server->name }}
                                @endif
                            </div>
                        </div>
                        @if($zone->is_open)
                            @can('floor.release_zone')
                                <button type="button" wire:click="confirmReleaseZone({{ $zone->id }})" class="text-base text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 min-h-[44px]">
                                    {{ __('billing.release_zone') }}
                                </button>
                            @endcan
                        @endif
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-base text-gray-500 dark:text-gray-400">{{ __('billing.no_zones') }}</div>
                @endforelse
            </div>
        </div>

        {{-- Orders --}}
        <div class="mb-6 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                <h2 class="font-semibold text-gray-900 dark:text-white">{{ __('billing.orders') }}</h2>
                <span class="text-base text-gray-500 dark:text-gray-400">{{ $group?->orderHeaders?->count() ?? 0 }}</span>
            </div>
            <div class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse($group?->orderHeaders ?? [] as $order)
                    <div class="px-4 py-3">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-base font-medium text-gray-900 dark:text-white">#{{ $order->id }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $order->ordered_at?->timezone(config('app.timezone'))->format('H:i') }}</span>
                                @if($order->occupiedZone)
                                    <span class="text-sm text-gray-400 dark:text-gray-500">{{ $order->occupiedZone->rangeLabel() }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @can('order.mark_delivered')
                                    @php
                                        $nonVoidedItems = $order->items->whereNull('voided_at');
                                        $allDelivered = $nonVoidedItems->isNotEmpty() && $nonVoidedItems->every(fn($i) => $i->delivered_at);
                                    @endphp
                                    @if($nonVoidedItems->isNotEmpty())
                                        <button
                                            type="button"
                                            wire:click="toggleOrderDelivered({{ $order->id }})"
                                            wire:target="toggleOrderDelivered({{ $order->id }})"
                                            wire:loading.attr="disabled"
                                            class="rounded-lg p-1 min-h-[36px] min-w-[36px] flex items-center justify-center transition-colors
                                                {{ $allDelivered
                                                    ? 'text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20'
                                                    : 'text-gray-300 hover:bg-gray-100 dark:text-gray-600 dark:hover:bg-gray-800' }}"
                                            title="{{ $allDelivered ? __('billing.mark_all_undelivered') : __('billing.mark_all_delivered') }}"
                                        >
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        </button>
                                    @endif
                                @endcan
                                @if($this->canVoidOrder($order) && $order->items->whereNull('voided_at')->whereNull('delivered_at')->isNotEmpty())
                                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                        <button type="button" @click="open = !open" class="rounded-lg p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-300 dark:hover:bg-gray-800 min-h-[36px] min-w-[36px] flex items-center justify-center">
                                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/>
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
                                            class="absolute right-0 z-20 mt-1 w-44 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-lg"
                                            style="display: none;"
                                        >
                                            <div class="py-1">
                                                <button
                                                    type="button"
                                                    wire:click="openVoidModal({{ $order->id }}, true)"
                                                    @click="open = false"
                                                    class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 min-h-[40px]"
                                                >
                                                    {{ __('billing.void_order') }}
                                                </button>
                                                @if($order->items->whereNull('voided_at')->count() > 1)
                                                    <button
                                                        type="button"
                                                        wire:click="openVoidModal({{ $order->id }}, false)"
                                                        @click="open = false"
                                                        class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 min-h-[40px]"
                                                    >
                                                        {{ __('billing.void_items') }}
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endif
                                <span class="text-sm rounded-full px-2 py-0.5 font-medium
                                    {{ $order->submission_status === 'SUBMITTED' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                    {{ $order->submission_status === 'PARTIALLY_VOIDED' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                                    {{ $order->submission_status === 'VOIDED' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : '' }}
                                ">{{ __('billing.status_'.strtolower($order->submission_status)) }}</span>
                            </div>
                        </div>
                        @if($order->notes)
                            <div class="mb-2 text-sm text-gray-500 dark:text-gray-400 italic">
                                {{ __('app.notes') }}: {{ $order->notes }}
                            </div>
                        @endif
                        <div class="space-y-1">
                            @foreach($order->items as $item)
                                <div class="flex items-center justify-between text-base">
                                    <div class="flex items-center gap-2">
                                        {{-- Delivery toggle (left side) --}}
                                        @if(!$item->voided_at)
                                            @can('order.mark_delivered')
                                                <button
                                                    type="button"
                                                    wire:click="toggleDelivered({{ $item->id }})"
                                                    wire:target="toggleDelivered({{ $item->id }})"
                                                    wire:loading.attr="disabled"
                                                    class="flex items-center justify-center min-h-[44px] min-w-[44px] rounded-lg transition-colors
                                                        {{ $item->delivered_at
                                                            ? 'text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-900/20'
                                                            : 'text-gray-300 hover:bg-gray-100 dark:text-gray-600 dark:hover:bg-gray-800' }}"
                                                    title="{{ $item->delivered_at ? __('billing.mark_undelivered') : __('billing.mark_delivered') }}"
                                                >
                                                    @if($item->delivered_at)
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                    @else
                                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                        </svg>
                                                    @endif
                                                </button>
                                            @endcan
                                        @endif
                                        <span class="{{ $item->voided_at ? 'line-through text-gray-400 dark:text-gray-600' : ($item->delivered_at ? 'text-gray-500 dark:text-gray-400' : 'text-gray-900 dark:text-gray-100') }}">
                                            {{ $item->quantity }}× {{ $item->menuItem?->display_name }}
                                            @if($item->variant_name)
                                                <span class="text-primary-500 dark:text-primary-400">{{ $item->variant_name }}</span>
                                            @endif
                                            @if($item->modifier_name)
                                                <span class="text-gray-400 dark:text-gray-500"> ({{ $item->modifier_name }})</span>
                                            @endif
                                        </span>
                                        @if($item->voided_at)
                                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing.voided') }}</span>
                                        @endif
                                    </div>
                                    @if($item->note)
                                        <div class="text-sm text-gray-400 dark:text-gray-500 italic mt-0.5">╺╸ {{ $item->note }}</div>
                                    @endif
                                    <span class="text-gray-500 dark:text-gray-400 {{ $item->voided_at ? 'line-through' : '' }}">{{ number_format($item->line_subtotal, 2) }} €</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-base text-gray-500 dark:text-gray-400">{{ __('billing.no_orders') }}</div>
                @endforelse
            </div>
        </div>

        {{-- Totals --}}
        <div class="mb-6 grid grid-cols-3 gap-3">
            <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 text-center">
                <div class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('billing.charges') }}</div>
                <div class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ number_format($this->chargesTotal, 2) }} €</div>
            </div>
            <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 text-center">
                <div class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('billing.paid') }}</div>
                <div class="mt-1 text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ number_format($this->paymentsTotal, 2) }} €</div>
            </div>
            <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 text-center">
                <div class="text-sm text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ __('app.balance') }}</div>
                <div class="mt-1 text-xl font-bold {{ $this->balance > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">{{ number_format($this->balance, 2) }} €</div>
            </div>
        </div>

        {{-- Payments --}}
        @if($group?->paymentRecords?->count() > 0)
            <div class="mb-6 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-white">{{ __('billing.payments') }}</h2>
                    <span class="text-base text-gray-500 dark:text-gray-400">{{ $group->paymentRecords?->count() ?? 0 }}</span>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach($group->paymentRecords as $payment)
                        <div class="px-4 py-3 flex items-center justify-between">
                            <div>
                                <div class="text-base text-gray-900 dark:text-white">{{ number_format($payment->amount, 2) }} €</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $payment->recorded_at?->timezone(config('app.timezone'))->format('H:i') }} · {{ $payment->payment_label }}</div>
                            </div>
                            @if($payment->is_voided)
                                <span class="text-sm text-red-500 dark:text-red-400">{{ __('billing.voided') }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Payment Form --}}
        @if(! $group?->is_closed)
            @can('payment.record')
                <div id="payment" class="mb-6 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-900 dark:text-white">{{ __('cashier.record_payment') }}</h2>
                    </div>
                    <div class="p-4 space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('cashier.amount') }}</label>
                                <div class="flex gap-2">
                                    <input id="payment-amount" type="number" wire:model="paymentAmount" step="0.01" min="0.01" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base h-11 px-3">
                                    @if($this->balance > 0)
                                        <button id="fill-balance" type="button" wire:click="fillBalance" class="rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 min-h-[44px] whitespace-nowrap transition-colors">
                                            {{ __('cashier.fill_balance') }}
                                        </button>
                                    @endif
                                </div>
                                @error('paymentAmount') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('cashier.payment_method') }}</label>
                                <input id="payment-label" type="text" wire:model="paymentLabel" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base h-11 px-3">
                            </div>
                        </div>
                        <div>
                            <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('app.notes') }}</label>
                            <textarea id="payment-notes" wire:model="paymentNotes" rows="2" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base p-3"></textarea>
                        </div>
                        <button type="button" wire:click="recordPayment" wire:target="recordPayment" wire:loading.attr="disabled" class="w-full flex justify-center items-center rounded-lg bg-primary-600 px-4 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 min-h-[48px] transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ __('cashier.record_payment') }}
                        </button>
                    </div>
                </div>
            @endcan
        @endif
    </div>

    {{-- Add Zone Modal --}}
    <div
        x-show="addZoneModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
        style="display: none;"
    >
        <div
            x-show="addZoneModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/50 dark:bg-black/70"
            @click="addZoneModal = false; $wire.closeAddZoneModal()"
        ></div>
        <div
            x-show="addZoneModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="transform translate-y-full sm:translate-y-4 sm:scale-95 opacity-0"
            x-transition:enter-end="transform translate-y-0 sm:scale-100 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="transform translate-y-0 sm:scale-100 opacity-100"
            x-transition:leave-end="transform translate-y-full sm:translate-y-4 sm:scale-95 opacity-0"
            class="relative w-full sm:w-[28rem] max-w-lg bg-white dark:bg-gray-900 rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto"
        >
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('billing.add_zone') }}</h2>
                    <button type="button" @click="addZoneModal = false; $wire.closeAddZoneModal()" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form wire:submit.prevent="addZone" class="space-y-4">
                    @if($this->shouldSelectAssignedServer())
                        <div>
                            <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('app.assign_server') }}</label>
                            <select id="zone-assigned-server-id" wire:model="assignedServerId" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base h-11 px-3">
                                <option value="">{{ __('app.select') }}</option>
                                @foreach($this->availableServers as $server)
                                    <option value="{{ $server->id }}">{{ $server->name ?: $server->username }}</option>
                                @endforeach
                            </select>
                            @error('assignedServerId') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('floor.row') }}</label>
                        <select wire:model="zoneRowId" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base h-11 px-3">
                            <option value="">{{ __('billing.select_row') }}</option>
                            @foreach(\App\Models\Row::with('section')->where('is_active', true)->get() as $r)
                                <option value="{{ $r->id }}">{{ $r->section?->name }} · {{ __('floor.row') }} {{ $r->row_code }}</option>
                            @endforeach
                        </select>
                        @error('zoneRowId') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('billing.start_pair') }}</label>
                            <input type="number" wire:model="zoneStartSeq" min="1" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base h-11 px-3">
                            @error('zoneStartSeq') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('billing.end_pair') }}</label>
                            <input type="number" wire:model="zoneEndSeq" min="1" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base h-11 px-3">
                            @error('zoneEndSeq') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('billing.delivery_label') }}</label>
                        <input type="text" wire:model="deliveryLabel" placeholder="{{ __('app.delivery_label_example') }}" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base h-11 px-3">
                    </div>
                    <div class="pt-2">
                        <button type="submit" wire:target="addZone" wire:loading.attr="disabled" class="w-full flex justify-center items-center rounded-lg bg-primary-600 px-4 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 min-h-[48px] transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ __('billing.add_zone') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Release Zone Confirmation Modal --}}
    <div
        x-show="releaseModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
        style="display: none;"
    >
        <div
            x-show="releaseModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/50 dark:bg-black/70"
            @click="$wire.cancelReleaseZone()"
        ></div>
        <div
            x-show="releaseModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="transform translate-y-full sm:translate-y-4 sm:scale-95 opacity-0"
            x-transition:enter-end="transform translate-y-0 sm:scale-100 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="transform translate-y-0 sm:scale-100 opacity-100"
            x-transition:leave-end="transform translate-y-full sm:translate-y-4 sm:scale-95 opacity-0"
            class="relative w-full sm:w-[24rem] max-w-lg bg-white dark:bg-gray-900 rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto"
        >
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('billing.release_zone') }}</h2>
                    <button type="button" @click="$wire.cancelReleaseZone()" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <p class="text-base text-gray-600 dark:text-gray-400 mb-6">{{ __('billing.release_confirm') }}</p>

                <div class="flex gap-3">
                    <button type="button" @click="$wire.cancelReleaseZone()" class="flex-1 rounded-lg bg-gray-100 dark:bg-gray-800 px-4 py-3 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 min-h-[48px] transition-colors">
                        {{ __('app.cancel') }}
                    </button>
                    <button type="button" wire:click="releaseZone" wire:target="releaseZone" wire:loading.attr="disabled" class="flex-1 rounded-lg bg-red-600 px-4 py-3 text-base font-semibold text-white hover:bg-red-500 min-h-[48px] transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        {{ __('billing.release_zone') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Void Confirmation Modal --}}
    <div x-show="voidModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center" style="display: none;">
        <div x-show="voidModal" x-transition.opacity class="fixed inset-0 bg-black/50 dark:bg-black/70" @click="voidModal = false; $wire.closeVoidModal()"></div>
        <div x-show="voidModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="transform translate-y-full sm:translate-y-4 sm:scale-95 opacity-0" x-transition:enter-end="transform translate-y-0 sm:scale-100 opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="transform translate-y-0 sm:scale-100 opacity-100" x-transition:leave-end="transform translate-y-full sm:translate-y-4 sm:scale-95 opacity-0" class="relative w-full sm:w-[28rem] max-w-lg bg-white dark:bg-gray-900 rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto">
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                        @if(! empty($this->selectedVoidItemIds) && count($this->selectedVoidItemIds) === collect($this->voidableItems)->count())
                            {{ __('billing.void_order') }} #{{ $this->voidOrderId }}
                        @else
                            {{ __('billing.void_items') }} — {{ __('billing.orders') }} #{{ $this->voidOrderId }}
                        @endif
                    </h2>
                    <button type="button" @click="voidModal = false; $wire.closeVoidModal()" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                @if(empty($this->voidableItems))
                    <p class="text-base text-gray-500 dark:text-gray-400 mb-4">{{ __('billing.no_voidable_items') }}</p>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ __('billing.select_items_to_void') }}</p>

                    <div class="space-y-1 mb-3">
                        @foreach($this->voidableItems as $item)
                            <label class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                                <input type="checkbox" wire:model="selectedVoidItemIds" value="{{ $item['id'] }}" class="h-5 w-5 rounded border-gray-300 dark:border-gray-700 text-primary-600 focus:ring-primary-500">
                                <span class="flex-1 text-base text-gray-900 dark:text-gray-100">
                                    {{ $item['quantity'] }}× {{ $item['menu_item']['display_name'] ?? '#' . $item['id'] }}
                                    @if($item['variant_name'] ?? null)
                                        <span class="text-primary-500 dark:text-primary-400">{{ $item['variant_name'] }}</span>
                                    @endif
                                </span>
                                <span class="text-base text-gray-500 dark:text-gray-400">{{ number_format($item['line_subtotal'], 2) }} €</span>
                            </label>
                        @endforeach
                    </div>

                    <button
                        type="button"
                        wire:click="openVoidModal({{ $this->voidOrderId }}, {{ empty($this->selectedVoidItemIds) ? 'true' : 'false' }})"
                        class="text-sm text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 mb-3"
                    >
                        {{ empty($this->selectedVoidItemIds) ? __('billing.select_all') : __('billing.deselect_all') }}
                    </button>
                @endif

                <label for="detail-void-reason" class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('billing.void_reason_optional') }}</label>
                <textarea id="detail-void-reason" wire:model="voidReason" rows="3" placeholder="{{ __('billing.void_reason_optional') }}" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base p-3"></textarea>
                @error('voidReason') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('billing.void_print_warning') }}</p>
                <div class="mt-4 flex gap-3">
                    <button type="button" @click="voidModal = false; $wire.closeVoidModal()" class="flex-1 rounded-lg bg-gray-100 dark:bg-gray-800 px-4 py-3 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 min-h-[48px] transition-colors">{{ __('app.cancel') }}</button>
                    <button
                        type="button"
                        wire:click="confirmVoid"
                        wire:target="confirmVoid"
                        wire:loading.attr="disabled"
                        @if(empty($this->selectedVoidItemIds)) disabled @endif
                        class="flex-1 rounded-lg bg-red-600 px-4 py-3 text-base font-semibold text-white hover:bg-red-500 min-h-[48px] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ empty($this->selectedVoidItemIds) ? __('billing.confirm_void') : __('billing.confirm_void_n_items', ['count' => count($this->selectedVoidItemIds)]) }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
