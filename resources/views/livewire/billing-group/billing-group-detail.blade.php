<div class="p-4 sm:p-6 lg:p-8" x-data="{ addZoneModal: @entangle('showAddZoneModal'), voidItemModal: @entangle('showVoidItemModal'), voidOrderModal: @entangle('showVoidOrderModal') }" wire:poll.10s="refreshData">
    <div class="max-w-4xl mx-auto">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('floor') }}" wire:navigate class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                </a>
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

            {{-- Favorite toggle (large, touch-friendly) --}}
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
            @else
                @can('billing_group.reopen')
                    <button type="button" wire:click="reopenGroup" wire:target="reopenGroup" wire:loading.attr="disabled" class="rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 min-h-[44px] flex items-center gap-1.5 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                        {{ __('cashier.reopen') }}
                    </button>
                @endcan
            @endif
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
                                <button type="button" wire:click="releaseZone({{ $zone->id }})" wire:confirm="{{ __('billing.release_confirm') }}" wire:target="releaseZone" wire:loading.attr="disabled" class="text-base text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 px-2 py-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 min-h-[44px] disabled:opacity-50 disabled:cursor-not-allowed">
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

        {{-- Orders --}}
        <div class="mb-6 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800">
                <h2 class="font-semibold text-gray-900 dark:text-white">{{ __('billing.orders') }}</h2>
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
                                @if($this->canVoidOrder($order) && $order->items->whereNull('voided_at')->isNotEmpty())
                                    <button type="button" wire:click="openVoidOrderModal({{ $order->id }})" wire:target="openVoidOrderModal" wire:loading.attr="disabled" class="rounded-lg border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20 disabled:opacity-50 disabled:cursor-not-allowed">
                                        {{ __('billing.void_order') }}
                                    </button>
                                @endif
                                <span class="text-sm rounded-full px-2 py-0.5 font-medium
                                    {{ $order->submission_status === 'SUBMITTED' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                    {{ $order->submission_status === 'PARTIALLY_VOIDED' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                                    {{ $order->submission_status === 'VOIDED' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : '' }}
                                ">{{ $order->submission_status }}</span>
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
                                        <span class="{{ $item->voided_at ? 'line-through text-gray-400 dark:text-gray-600' : 'text-gray-900 dark:text-gray-100' }}">
                                            {{ $item->quantity }}× {{ $item->menuItem?->display_name }}
                                            @if($item->variant_name)
                                                <span class="text-primary-500 dark:text-primary-400">{{ $item->variant_name }}</span>
                                            @endif
                                            @if($item->modifier_name)
                                                <span class="text-gray-400 dark:text-gray-500"> ({{ $item->modifier_name }})</span>
                                            @endif
                                        </span>
                                        @if($item->voided_at)
                                            <span class="text-sm text-red-500 dark:text-red-400">{{ __('billing.voided') }}</span>
                                        @elseif($this->canVoidItem($item))
                                            <button type="button" wire:click="openVoidItemModal({{ $item->id }})" wire:target="openVoidItemModal" wire:loading.attr="disabled" class="rounded-lg border border-red-300 px-2 py-1 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20 disabled:opacity-50 disabled:cursor-not-allowed">
                                                {{ __('billing.void_item') }}
                                            </button>
                                        @endif
                                    </div>
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

        {{-- Payments --}}
        @if($group?->paymentRecords?->count() > 0)
            <div class="mb-6 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800">
                    <h2 class="font-semibold text-gray-900 dark:text-white">{{ __('billing.payments') }}</h2>
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

                @if($errorMessage)
                    <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 p-3 text-base text-red-600 dark:text-red-400">{{ $errorMessage }}</div>
                @endif

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

    {{-- Void Item Modal --}}
    <div x-show="voidItemModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center" style="display: none;">
        <div x-show="voidItemModal" class="fixed inset-0 bg-black/50 dark:bg-black/70" @click="voidItemModal = false; $wire.closeVoidModal()"></div>
        <div x-show="voidItemModal" class="relative w-full sm:w-[28rem] max-w-lg bg-white dark:bg-gray-900 rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto">
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('billing.void_item') }}</h2>
                    <button type="button" @click="voidItemModal = false; $wire.closeVoidModal()" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center">×</button>
                </div>
                <label for="billing-void-item-reason" class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('billing.void_reason') }}</label>
                <textarea id="billing-void-item-reason" wire:model="voidReason" rows="3" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base p-3"></textarea>
                @error('voidReason') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('billing.void_print_warning') }}</p>
                <div class="mt-4 flex gap-3">
                    <button type="button" @click="voidItemModal = false; $wire.closeVoidModal()" class="flex-1 rounded-lg bg-gray-100 dark:bg-gray-800 px-4 py-3 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 min-h-[48px] transition-colors">{{ __('app.cancel') }}</button>
                    <button type="button" wire:click="confirmVoidItem" wire:target="confirmVoidItem" wire:loading.attr="disabled" class="flex-1 rounded-lg bg-red-600 px-4 py-3 text-base font-semibold text-white hover:bg-red-500 min-h-[48px] transition-colors disabled:opacity-50 disabled:cursor-not-allowed">{{ __('billing.confirm_void_item') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Void Order Modal --}}
    <div x-show="voidOrderModal" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center" style="display: none;">
        <div x-show="voidOrderModal" class="fixed inset-0 bg-black/50 dark:bg-black/70" @click="voidOrderModal = false; $wire.closeVoidModal()"></div>
        <div x-show="voidOrderModal" class="relative w-full sm:w-[28rem] max-w-lg bg-white dark:bg-gray-900 rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto">
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('billing.void_order') }}</h2>
                    <button type="button" @click="voidOrderModal = false; $wire.closeVoidModal()" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center">×</button>
                </div>
                <label for="billing-void-order-reason" class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('billing.void_reason') }}</label>
                <textarea id="billing-void-order-reason" wire:model="voidReason" rows="3" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base p-3"></textarea>
                @error('voidReason') <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('billing.void_order_warning') }}</p>
                <div class="mt-4 flex gap-3">
                    <button type="button" @click="voidOrderModal = false; $wire.closeVoidModal()" class="flex-1 rounded-lg bg-gray-100 dark:bg-gray-800 px-4 py-3 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 min-h-[48px] transition-colors">{{ __('app.cancel') }}</button>
                    <button type="button" wire:click="confirmVoidOrder" wire:target="confirmVoidOrder" wire:loading.attr="disabled" class="flex-1 rounded-lg bg-red-600 px-4 py-3 text-base font-semibold text-white hover:bg-red-500 min-h-[48px] transition-colors disabled:opacity-50 disabled:cursor-not-allowed">{{ __('billing.confirm_void_order') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
