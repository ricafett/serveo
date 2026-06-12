<div
    x-data="orderEntry(@js($menuItemsData), @js($menuCategoriesData), {{ $defaultCategoryId }})"
    @order-submitted.window="cart = []"
    @open-note-modal.window="openNoteModal($event.detail.index)"
    @save-note.window="saveNote()"
    @delete-note.window="deleteNote($event.detail.index)"
    class="p-4 sm:p-6 lg:p-8"
>
    <div class="max-w-4xl mx-auto">
        {{-- Header --}}
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="cart.length ? showLeaveConfirm = true : window.history.back()"
                    class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('order.order_entry') }}</h1>
                    @if($this->group)
                        <p class="text-base text-gray-500 dark:text-gray-400">{{ $this->group->longLabel() }}</p>
                    @endif
                </div>
            </div>
            @if($this->group?->is_closed)
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-sm font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('app.closed') }}</span>
            @endif
        </div>

        {{-- Messages --}}
        @if($errorMessage)
            <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 p-3 text-base text-red-600 dark:text-red-400">
                {{ $errorMessage }}
            </div>
        @endif
        @if($successMessage)
            <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-base text-green-600 dark:text-green-400">
                {{ $successMessage }}
            </div>
        @endif

        {{-- Tab Bar (mobile only) --}}
        <div class="lg:hidden mb-4 flex rounded-lg bg-gray-100 dark:bg-gray-800 p-1">
            <button
                type="button"
                @click="activeTab = 'menu'"
                class="flex-1 rounded-md px-4 py-2.5 text-base font-medium min-h-[44px] transition-colors"
                :class="activeTab === 'menu' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400'"
            >
                {{ __('order.menu_tab') }}
            </button>
            <button
                type="button"
                @click="activeTab = 'order'"
                class="flex-1 rounded-md px-4 py-2.5 text-base font-medium min-h-[44px] transition-colors flex items-center justify-center gap-1.5"
                :class="activeTab === 'order' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400'"
            >
                {{ __('order.order_tab') }}
                <span
                    x-show="cartItemCount > 0"
                    x-text="'(' + cartItemCount + ')'"
                    class="text-sm opacity-75"
                ></span>
            </button>
        </div>

        {{-- Content Area --}}
        <div class="lg:flex lg:flex-row lg:gap-6 lg:min-h-0">
            {{-- ======================================== --}}
            {{-- Menu Panel --}}
            {{-- ======================================== --}}
            <div x-show="activeTab === 'menu'" class="lg:!block lg:flex-1 lg:min-w-0 pb-20 sm:pb-6 lg:pb-0">
                {{-- Menu Categories --}}
                <div class="mb-4">
                    <div class="flex gap-2 overflow-x-auto pb-2 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:-mx-0 lg:px-0">
                        <template x-for="category in menuCategories" :key="category.id">
                            <button
                                type="button"
                                @click="selectCategory(category.id)"
                                class="shrink-0 rounded-lg px-4 py-2.5 text-base font-medium min-h-[44px] transition-colors"
                                :class="selectedCategoryId === category.id
                                    ? 'bg-primary-600 text-white'
                                    : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'"
                                x-text="category.display_name"
                            ></button>
                        </template>
                    </div>
                </div>

                {{-- Menu Items Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    <template x-for="menuItem in filteredItems" :key="menuItem.id">
                        <button
                            type="button"
                            @click="handleItemTap(menuItem)"
                            class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 text-left hover:border-primary-300 dark:hover:border-primary-700 transition-colors min-h-[80px] flex flex-col justify-between"
                        >
                            <div>
                                <div class="text-base font-medium text-gray-900 dark:text-white leading-tight" x-text="menuItem.display_name"></div>
                                <template x-if="menuItem.has_variants || menuItem.modifier_set">
                                    <div class="mt-1 text-xs text-primary-500 dark:text-primary-400">
                                        <template x-if="menuItem.has_variants && menuItem.modifier_set">
                                            <span>{{ __('order.has_variants_and_modifiers') }}</span>
                                        </template>
                                        <template x-if="menuItem.has_variants && !menuItem.modifier_set">
                                            <span>{{ __('order.has_variants') }}</span>
                                        </template>
                                        <template x-if="!menuItem.has_variants && menuItem.modifier_set">
                                            <span>{{ __('order.has_modifiers') }}</span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-base text-gray-500 dark:text-gray-400">
                                    <template x-if="getItemTotalQuantity(menuItem.id) > 0">
                                        <span x-text="(menuItem.unit_price * getItemTotalQuantity(menuItem.id)).toFixed(2) + ' €'"></span>
                                    </template>
                                    <template x-if="getItemTotalQuantity(menuItem.id) === 0">
                                        <span x-text="menuItem.unit_price.toFixed(2) + ' €'"></span>
                                    </template>
                                </span>
                                <template x-if="getItemTotalQuantity(menuItem.id) > 0">
                                    <span
                                        class="rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 min-w-[1.75rem] h-7 flex items-center justify-center text-base font-bold px-1.5"
                                        x-text="'×' + getItemTotalQuantity(menuItem.id)"
                                    ></span>
                                </template>
                            </div>
                        </button>
                    </template>
                    <template x-if="filteredItems.length === 0">
                        <div class="col-span-full text-center py-8 text-base text-gray-500 dark:text-gray-400">
                            {{ __('order.no_items') }}
                        </div>
                    </template>
                </div>
            </div>

            {{-- ======================================== --}}
            {{-- Order Panel --}}
            {{-- ======================================== --}}
            <div x-show="activeTab === 'order'" class="lg:!block lg:w-80 lg:shrink-0 pb-20 sm:pb-6 lg:pb-0">
                <div class="lg:sticky lg:top-4">

                    {{-- Delivery (collapsible single line) --}}
                    @if($this->zones->count() > 0)
                        <div
                            class="mb-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden"
                            x-data="{ deliveryOpen: false }"
                        >
                            <button
                                type="button"
                                @click="deliveryOpen = !deliveryOpen"
                                    class="w-full px-4 py-3 flex items-center justify-between text-base font-medium text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-800 min-h-[44px]"
                            >
                                <span>{{ __('order.delivery') }}</span>
                                <div class="flex items-center gap-2">
                                    {{-- Collapsed summary --}}
                                    <span x-show="!deliveryOpen" class="text-sm font-normal text-gray-500 dark:text-gray-400 truncate max-w-[180px]">
                                        @if($selectedZoneId === null)
                                            {{ __('order.group_level') }}
                                        @else
                                            {{ $this->selectedZone?->rangeLabel() }}
                                                @if($this->selectedDeliveryPair)
                                                    · {{ $this->selectedDeliveryPair->pair_sequence }}
                                                @endif
                                        @endif
                                    </span>
                                    <svg class="h-4 w-4 text-gray-400 transition-transform shrink-0" :class="deliveryOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>
                            </button>
                            <div x-show="deliveryOpen" x-collapse>
                                <div class="px-4 pt-1 pb-8 space-y-3">
                                    {{-- Zone Selector --}}
                                    <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">{{ __('order.delivery_zone') }}</label>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            wire:click="setZone(null)"
                                            class="rounded-lg px-3 py-2 text-base font-medium min-h-[44px] transition-colors {{ $selectedZoneId === null ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 ring-1 ring-primary-300 dark:ring-primary-700 hover:bg-primary-200 dark:hover:bg-primary-900/50' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                                        >
                                            {{ __('order.group_level') }}
                                        </button>
                                        @foreach($this->zones as $zone)
                                            <button
                                                type="button"
                                                wire:click="setZone({{ $zone->id }})"
                                                class="rounded-lg px-3 py-2 text-base font-medium min-h-[44px] transition-colors {{ $selectedZoneId === $zone->id ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 ring-1 ring-primary-300 dark:ring-primary-700 hover:bg-primary-200 dark:hover:bg-primary-900/50' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                                            >
                                                {{ $zone->rangeLabel() }}
                                            </button>
                                        @endforeach
                                    </div>

                                    {{-- Delivery Pair Override --}}
                                    @if($this->selectedZone)
                                        <label class="block text-sm font-medium text-gray-500 dark:text-gray-400 mt-3 mb-2">{{ __('order.seat_pair') }}</label>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                wire:click="setDeliveryPair(null)"
                                                class="rounded-lg px-3 py-2 text-base font-medium min-h-[44px] transition-colors {{ $selectedDeliveryPairId === null ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 ring-1 ring-primary-300 dark:ring-primary-700 hover:bg-primary-200 dark:hover:bg-primary-900/50' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                                            >
                                                {{ __('order.unspecified') }}
                                            </button>
                                            @foreach($this->selectedZone->row?->seatPairs ?? [] as $pair)
                                                @if($pair->pair_sequence >= $this->selectedZone->start_seat_pair_sequence && $pair->pair_sequence <= $this->selectedZone->end_seat_pair_sequence)
                                                    <button
                                                        type="button"
                                                        wire:click="setDeliveryPair({{ $pair->id }})"
                                                        class="rounded-lg px-3 py-2 text-base font-medium min-h-[44px] transition-colors {{ $selectedDeliveryPairId === $pair->id ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 ring-1 ring-primary-300 dark:ring-primary-700 hover:bg-primary-200 dark:hover:bg-primary-900/50' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                                                    >
                                                        {{ $pair->pair_sequence }}
                                                    </button>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Order Items --}}
                    <div class="mb-4 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <template x-if="cart.length > 0">
                            <div>
                                <div class="divide-y divide-gray-200 dark:divide-gray-800">
                                    <template x-for="(item, index) in cart" :key="item.cart_key">
                                        <div class="px-4 py-3">
                                            <div class="flex items-center justify-between">
                                                <div class="min-w-0 flex-1">
                                                    <div class="text-base font-medium text-gray-900 dark:text-white truncate" x-text="item.display_name"></div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        <template x-if="item.variant_name">
                                                            <span class="text-primary-500 dark:text-primary-400" x-text="item.variant_name"></span>
                                                        </template>
                                                        <template x-if="item.modifier_name">
                                                            <span class="text-gray-400 dark:text-gray-500" x-text="' (' + item.modifier_name + ')'"></span>
                                                        </template>
                                                        <template x-if="item.variant_name || item.modifier_name">
                                                            <span class="mx-1 text-gray-300 dark:text-gray-600">—</span>
                                                        </template>
                                                        <span x-text="item.unit_price.toFixed(2) + ' €'"></span>
                                                        <span x-show="item.quantity > 1" class="ml-1 text-gray-400 dark:text-gray-500">→</span>
                                                        <span x-show="item.quantity > 1" class="ml-1 font-medium text-gray-700 dark:text-gray-300" x-text="(item.unit_price * item.quantity).toFixed(2) + ' €'"></span>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-1 ml-3">
                                                    <button
                                                        type="button"
                                                        @click="decrement(index)"
                                                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                                                    </button>
                                                    <span class="text-base font-semibold text-gray-900 dark:text-white w-6 text-center" x-text="item.quantity"></span>
                                                    <button
                                                        type="button"
                                                        @click="increment(index)"
                                                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center"
                                                    >
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                                    </button>
                                                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                                        <button
                                                            type="button"
                                                            dusk="cart-item-menu"
                                                            @click="open = !open"
                                                            class="rounded-lg p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-300 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center"
                                                        >
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
                                                            class="absolute right-0 z-20 mt-1 w-40 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 shadow-lg"
                                                            style="display: none;"
                                                        >
                                                            <div class="py-1">
                                                <button
                                                    type="button"
                                                    dusk="add-note-btn"
                                                    @click="$dispatch('open-note-modal', { index })"
                                                    class="block w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 min-h-[40px]"
                                                    x-text="item.note ? '{{ __('order.edit_note') }}' : '{{ __('order.add_note') }}'"
                                                ></button>
                                                                <template x-if="item.note">
                                                                    <button
                                                                        type="button"
                                                                        dusk="delete-note-btn"
                                                                        @click="$dispatch('delete-note', { index })"
                                                                        class="block w-full text-left px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700 min-h-[40px]"
                                                                    >{{ __('order.delete_note') }}</button>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <template x-if="item.note">
                                                <div class="text-sm text-gray-400 dark:text-gray-500 italic mt-1" x-text="'\u257A\u2578 ' + item.note"></div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <template x-if="cart.length === 0">
                            <div class="px-4 py-8 text-center text-base text-gray-500 dark:text-gray-400">
                                {{ __('order.empty_cart') }}
                            </div>
                        </template>
                    </div>

                    {{-- Notes --}}
                    <div class="mb-0">
                        <label for="order-notes" class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('app.notes') }}</label>
                        <textarea id="order-notes" wire:model="notes" rows="2" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base p-3"></textarea>
                    </div>

                </div>
            </div>
        </div>

        {{-- Submit button: fixed above nav on mobile, inline on desktop --}}
        <div class="sm:static sm:mt-4 sm:bg-transparent sm:border-0 sm:p-0 fixed bottom-14 left-0 right-0 px-4 py-3 bg-gray-50/95 dark:bg-gray-950/95 backdrop-blur border-t border-gray-200 dark:border-gray-800 z-30">
            <button
                type="button"
                @click="cart.length && $wire.call('submitOrder', cart.map(function(i) { return { menu_item_id: i.menu_item_id, quantity: i.quantity, variant_name: i.variant_name, modifier_name: i.modifier_name, note: i.note }; }))"
                :disabled="{{ $this->group?->is_closed ? 'true' : 'false' }} || cart.length === 0"
                wire:target="submitOrder"
                wire:loading.attr="disabled"
                class="w-full flex justify-center items-center rounded-lg bg-primary-600 px-4 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 min-h-[48px] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {{ __('order.submit') }}
                <template x-if="cartItemCount > 0">
                    <span class="ml-2 text-sm opacity-75" x-text="'(' + cartItemCount + ' · ' + cartTotal.toFixed(2) + ' €)'"></span>
                </template>
            </button>
        </div>
    </div>

    {{-- Variant / Modifier Selection Modal --}}
    <div
        x-show="showModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div
            x-show="showModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/50 dark:bg-black/70"
            @click="closeModal()"
        ></div>

        {{-- Sheet --}}
        <div
            x-show="showModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="transform translate-y-full sm:translate-y-4 sm:scale-95 opacity-0"
            x-transition:enter-end="transform translate-y-0 sm:scale-100 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="transform translate-y-0 sm:scale-100 opacity-100"
            x-transition:leave-end="transform translate-y-full sm:translate-y-4 sm:scale-95 opacity-0"
            class="relative w-[calc(100%-2rem)] sm:w-[24rem] max-w-lg bg-white dark:bg-gray-900 rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto"
        >
            <div class="p-4 sm:p-6">
                {{-- Header --}}
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white" x-text="modalItem ? modalItem.display_name : ''"></h3>
                    <button
                        type="button"
                        @click="closeModal()"
                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                {{-- Variant Selector --}}
                <template x-if="modalItem && modalItem.has_variants">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('order.select_variant') }}</label>
                        <div class="space-y-1">
                            <template x-for="variant in modalItem.variants" :key="variant.id">
                                <label class="flex items-center gap-3 rounded-lg px-3 py-2.5 min-h-[44px] cursor-pointer transition-colors"
                                    :class="modalSelectedVariant === variant.display_name
                                        ? 'bg-primary-50 dark:bg-primary-900/20 ring-1 ring-primary-300 dark:ring-primary-700'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                                >
                                    <input
                                        type="radio"
                                        :name="'variant_' + modalItem.id"
                                        :value="variant.display_name"
                                        x-model="modalSelectedVariant"
                                        class="sr-only"
                                    >
                                    <span
                                        class="flex-shrink-0 h-5 w-5 rounded-full border-2 flex items-center justify-center transition-colors"
                                        :class="modalSelectedVariant === variant.display_name
                                            ? 'border-primary-600 dark:border-primary-400 bg-primary-600 dark:bg-primary-400'
                                            : 'border-gray-300 dark:border-gray-600'"
                                    >
                                        <span
                                            class="h-2.5 w-2.5 rounded-full bg-white"
                                            x-show="modalSelectedVariant === variant.display_name"
                                        ></span>
                                    </span>
                                    <span class="text-base text-gray-900 dark:text-white" x-text="variant.display_name"></span>
                                </label>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Modifier Selector --}}
                <template x-if="modalItem && modalItem.modifier_set">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2" x-text="modalItem.modifier_set.display_name"></label>
                        <template x-if="modalItem.modifier_set.selection_mode === 'single'">
                            <div class="space-y-1">
                                <label class="flex items-center gap-3 rounded-lg px-3 py-2.5 min-h-[44px] cursor-pointer transition-colors"
                                    :class="modalSelectedModifiers.length === 0 ? 'bg-primary-50 dark:bg-primary-900/20 ring-1 ring-primary-300 dark:ring-primary-700' : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                                >
                                    <input
                                        type="radio"
                                        :name="'modifier_' + modalItem.id"
                                        value=""
                                        x-model="modalSelectedModifierSingle"
                                        class="sr-only"
                                    >
                                    <span
                                        class="flex-shrink-0 h-5 w-5 rounded-full border-2 flex items-center justify-center transition-colors"
                                        :class="!modalSelectedModifierSingle
                                            ? 'border-primary-600 dark:border-primary-400 bg-primary-600 dark:bg-primary-400'
                                            : 'border-gray-300 dark:border-gray-600'"
                                    >
                                        <span
                                            class="h-2.5 w-2.5 rounded-full bg-white"
                                            x-show="!modalSelectedModifierSingle"
                                        ></span>
                                    </span>
                                    <span class="text-base text-gray-500 dark:text-gray-400">{{ __('order.no_modifier') }}</span>
                                </label>
                                <template x-for="modifier in modalItem.modifier_set.items" :key="modifier.id">
                                    <label class="flex items-center gap-3 rounded-lg px-3 py-2.5 min-h-[44px] cursor-pointer transition-colors"
                                        :class="modalSelectedModifierSingle === modifier.display_name
                                            ? 'bg-primary-50 dark:bg-primary-900/20 ring-1 ring-primary-300 dark:ring-primary-700'
                                            : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                                    >
                                        <input
                                            type="radio"
                                            :name="'modifier_' + modalItem.id"
                                            :value="modifier.display_name"
                                            x-model="modalSelectedModifierSingle"
                                            class="sr-only"
                                        >
                                        <span
                                            class="flex-shrink-0 h-5 w-5 rounded-full border-2 flex items-center justify-center transition-colors"
                                            :class="modalSelectedModifierSingle === modifier.display_name
                                                ? 'border-primary-600 dark:border-primary-400 bg-primary-600 dark:bg-primary-400'
                                                : 'border-gray-300 dark:border-gray-600'"
                                        >
                                            <span
                                                class="h-2.5 w-2.5 rounded-full bg-white"
                                                x-show="modalSelectedModifierSingle === modifier.display_name"
                                            ></span>
                                        </span>
                                        <span class="text-base text-gray-900 dark:text-white" x-text="modifier.display_name"></span>
                                    </label>
                                </template>
                            </div>
                        </template>
                        <template x-if="modalItem.modifier_set.selection_mode === 'multiple'">
                            <div class="space-y-1">
                                <template x-for="modifier in modalItem.modifier_set.items" :key="modifier.id">
                                    <label class="flex items-center gap-3 rounded-lg px-3 py-2.5 min-h-[44px] cursor-pointer transition-colors"
                                        :class="modalSelectedModifiers.includes(modifier.display_name)
                                            ? 'bg-primary-50 dark:bg-primary-900/20 ring-1 ring-primary-300 dark:ring-primary-700'
                                            : 'hover:bg-gray-50 dark:hover:bg-gray-800'"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="modifier.display_name"
                                            x-model="modalSelectedModifiers"
                                            class="sr-only"
                                        >
                                        <span
                                            class="flex-shrink-0 h-5 w-5 rounded border-2 flex items-center justify-center transition-colors"
                                            :class="modalSelectedModifiers.includes(modifier.display_name)
                                                ? 'border-primary-600 dark:border-primary-400 bg-primary-600 dark:bg-primary-400'
                                                : 'border-gray-300 dark:border-gray-600'"
                                        >
                                            <svg
                                                x-show="modalSelectedModifiers.includes(modifier.display_name)"
                                                class="h-3 w-3 text-white"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </span>
                                        <span class="text-base text-gray-900 dark:text-white" x-text="modifier.display_name"></span>
                                    </label>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                {{-- Actions --}}
                <div class="flex gap-3 mt-6">
                    <button
                        type="button"
                        @click="closeModal()"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 min-h-[44px] transition-colors"
                    >
                        {{ __('app.cancel') }}
                    </button>
                    <button
                        type="button"
                        @click="confirmModal()"
                        :disabled="modalItem && modalItem.has_variants && !modalSelectedVariant"
                        class="flex-1 rounded-lg bg-primary-600 px-4 py-2.5 text-base font-semibold text-white hover:bg-primary-500 min-h-[44px] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ __('order.add_to_order') }}
                    </button>
                </div>
            </div>
        </div>
    {{-- Note Modal --}}
    <div
        id="note-modal"
        dusk="note-modal"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
        style="display: none;"
    >
        <div
            dusk="note-modal-backdrop"
            class="fixed inset-0 bg-black/50 dark:bg-black/70"
            @click="closeNoteModal()"
        ></div>

        <div
            class="relative w-full sm:w-[24rem] max-w-lg bg-white dark:bg-gray-900 rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto"
        >
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ __('order.note_for') }} <span x-text="noteModalItemName"></span>
                    </h3>
                    <button
                        type="button"
                        @click="closeNoteModal()"
                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('order.item_note_label') }}</label>
                    <textarea
                        id="note-modal-text"
                        x-model="noteModalText"
                        rows="4"
                        maxlength="200"
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base p-3"
                        placeholder="{{ __('order.item_note_placeholder') }}"
                    ></textarea>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500" x-text="noteModalText.length + ' / 200'"></p>
                </div>

                <div class="flex gap-3">
                    <button
                        type="button"
                        @click="closeNoteModal()"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 min-h-[44px] transition-colors"
                    >
                        {{ __('app.cancel') }}
                    </button>
                    <button
                        type="button"
                        dusk="save-note"
                        @click="$dispatch('save-note')"
                        class="flex-1 rounded-lg bg-primary-600 px-4 py-2.5 text-base font-semibold text-white hover:bg-primary-500 min-h-[44px] transition-colors"
                    >
                        {{ __('app.save') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Leave Confirmation Modal --}}
    <div
        x-show="showLeaveConfirm"
        x-cloak
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
        style="display: none;"
    >
        <div
            x-show="showLeaveConfirm"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/50 dark:bg-black/70"
            @click="showLeaveConfirm = false"
        ></div>

        <div
            x-show="showLeaveConfirm"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="transform translate-y-full sm:translate-y-4 sm:scale-95 opacity-0"
            x-transition:enter-end="transform translate-y-0 sm:scale-100 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="transform translate-y-0 sm:scale-100 opacity-100"
            x-transition:leave-end="transform translate-y-full sm:translate-y-4 sm:scale-95 opacity-0"
            class="relative w-full sm:w-[24rem] max-w-lg bg-white dark:bg-gray-900 rounded-t-2xl sm:rounded-2xl shadow-xl"
        >
            <div class="p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ __('order.unsaved_cart_title') }}
                    </h3>
                    <button
                        type="button"
                        @click="showLeaveConfirm = false"
                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <p class="text-base text-gray-600 dark:text-gray-400 mb-6" x-text="'{{ __('order.unsaved_cart_body') }}'.replace(':count', cartItemCount)"></p>

                <div class="flex gap-3">
                    <button
                        type="button"
                        @click="showLeaveConfirm = false"
                        class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 px-4 py-2.5 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 min-h-[44px] transition-colors"
                    >
                        {{ __('app.cancel') }}
                    </button>
                    <button
                        type="button"
                        @click="discardAndLeave()"
                        class="flex-1 rounded-lg bg-red-600 px-4 py-2.5 text-base font-semibold text-white hover:bg-red-500 min-h-[44px] transition-colors"
                    >
                        {{ __('order.discard') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function orderEntry(menuItems, menuCategories, defaultCategoryId) {
    return {
        cart: [],
        selectedCategoryId: defaultCategoryId,
        menuItems: menuItems,
        menuCategories: menuCategories,
        activeTab: 'menu',

        // Modal state
        showModal: false,
        modalItem: null,
        modalSelectedVariant: '',
        modalSelectedModifierSingle: '',
        modalSelectedModifiers: [],

        // Note modal state
        showNoteModal: false,
        noteModalItemIndex: null,
        noteModalText: '',
        noteModalItemName: '',

        // Leave confirmation state
        showLeaveConfirm: false,
        leaveCleanupDone: false,

        init() {
            var self = this;

            this.beforeunloadHandler = function(e) {
                if (self.cart.length > 0) {
                    e.preventDefault();
                }
            };
            window.addEventListener('beforeunload', this.beforeunloadHandler);

            this.navigatingHandler = function(e) {
                if (self.cart.length > 0) {
                    e.preventDefault();
                    self.showLeaveConfirm = true;
                }
            };
            document.addEventListener('livewire:navigating', this.navigatingHandler);
        },

        cleanupLeaveGuards() {
            if (this.beforeunloadHandler) {
                window.removeEventListener('beforeunload', this.beforeunloadHandler);
            }
            if (this.navigatingHandler) {
                document.removeEventListener('livewire:navigating', this.navigatingHandler);
            }
            this.leaveCleanupDone = true;
        },

        discardAndLeave() {
            this.cleanupLeaveGuards();
            window.history.back();
        },

        get filteredItems() {
            if (!this.selectedCategoryId) return this.menuItems;
            return this.menuItems.filter(function (i) { return i.category_id === this.selectedCategoryId; }.bind(this));
        },

        get cartItemCount() {
            return this.cart.reduce(function (sum, i) { return sum + i.quantity; }, 0);
        },

        get cartTotal() {
            return this.cart.reduce(function (sum, i) { return sum + (i.unit_price * i.quantity); }, 0);
        },

        getItemTotalQuantity(menuItemId) {
            return this.cart.reduce(function (sum, i) {
                if (i.menu_item_id === menuItemId) return sum + i.quantity;
                return sum;
            }, 0);
        },

        handleItemTap(menuItem) {
            // If assume_default with a default modifier and no variants, add directly
            if (!menuItem.has_variants && menuItem.modifier_set && menuItem.modifier_set.assume_default && menuItem.modifier_set.default_modifier_display_name) {
                this.addToCartWithModifier(menuItem, menuItem.modifier_set.default_modifier_display_name);
                return;
            }

            if (menuItem.has_variants || menuItem.modifier_set) {
                this.openModal(menuItem);
            } else {
                this.addToCartSimple(menuItem);
            }
        },

        openModal(menuItem) {
            this.modalItem = menuItem;
            this.modalSelectedVariant = '';
            this.modalSelectedModifierSingle = '';
            this.modalSelectedModifiers = [];

            // Pre-select default modifier when assume_default is true
            if (menuItem.modifier_set && menuItem.modifier_set.assume_default && menuItem.modifier_set.default_modifier_display_name) {
                if (menuItem.modifier_set.selection_mode === 'single') {
                    this.modalSelectedModifierSingle = menuItem.modifier_set.default_modifier_display_name;
                } else {
                    this.modalSelectedModifiers = [menuItem.modifier_set.default_modifier_display_name];
                }
            }

            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.modalItem = null;
            this.modalSelectedVariant = '';
            this.modalSelectedModifierSingle = '';
            this.modalSelectedModifiers = [];
        },

        confirmModal() {
            if (!this.modalItem) return;

            var variantName = this.modalSelectedVariant || null;
            if (this.modalItem.has_variants && !variantName) return;

            var modifierName = null;
            if (this.modalItem.modifier_set) {
                if (this.modalItem.modifier_set.selection_mode === 'single') {
                    modifierName = this.modalSelectedModifierSingle || null;
                } else {
                    modifierName = this.modalSelectedModifiers.length > 0 ? this.modalSelectedModifiers.join(', ') : null;
                }
            }

            var cartKey = this.modalItem.id + '|' + (variantName || '') + '|' + (modifierName || '') + '|';
            var existing = this.cart.find(function (i) { return i.cart_key === cartKey; });
            if (existing) {
                existing.quantity++;
            } else {
                this.cart.push({
                    cart_key: cartKey,
                    menu_item_id: this.modalItem.id,
                    display_name: this.modalItem.display_name,
                    unit_price: this.modalItem.unit_price,
                    quantity: 1,
                    route_type: this.modalItem.route_type,
                    variant_name: variantName,
                    modifier_name: modifierName,
                    note: null,
                });
            }

            this.closeModal();
        },

        addToCartWithModifier(menuItem, modifierName) {
            var cartKey = menuItem.id + '||' + modifierName + '|';
            var existing = this.cart.find(function (i) { return i.cart_key === cartKey; });
            if (existing) {
                existing.quantity++;
            } else {
                this.cart.push({
                    cart_key: cartKey,
                    menu_item_id: menuItem.id,
                    display_name: menuItem.display_name,
                    unit_price: menuItem.unit_price,
                    quantity: 1,
                    route_type: menuItem.route_type,
                    variant_name: null,
                    modifier_name: modifierName,
                    note: null,
                });
            }
        },

        addToCartSimple(menuItem) {
            var cartKey = menuItem.id + '|||';
            var existing = this.cart.find(function (i) { return i.cart_key === cartKey; });
            if (existing) {
                existing.quantity++;
            } else {
                this.cart.push({
                    cart_key: cartKey,
                    menu_item_id: menuItem.id,
                    display_name: menuItem.display_name,
                    unit_price: menuItem.unit_price,
                    quantity: 1,
                    route_type: menuItem.route_type,
                    variant_name: null,
                    modifier_name: null,
                    note: null,
                });
            }
        },

        increment(index) {
            this.cart[index].quantity++;
        },

        decrement(index) {
            if (this.cart[index].quantity > 1) {
                this.cart[index].quantity--;
            } else {
                this.cart.splice(index, 1);
            }
        },

        openNoteModal(index) {
            var item = this.cart[index];
            if (!item) return;
            this.noteModalItemIndex = index;
            this.noteModalText = item.note || '';
            this.noteModalItemName = item.display_name;

            // Direct DOM via getElementById — bypasses all Alpine/Livewire rendering
            var modal = document.getElementById('note-modal');
            if (!modal) return;

            modal.style.display = 'flex';
            modal.style.opacity = '0';
            // Force reflow then animate
            modal.offsetHeight;
            modal.style.transition = 'opacity 200ms ease-out';
            modal.style.opacity = '1';
        },

        closeNoteModal() {
            var modal = document.getElementById('note-modal');
            if (modal) {
                modal.style.transition = 'opacity 150ms ease-in';
                modal.style.opacity = '0';
                var self = this;
                setTimeout(function () {
                    modal.style.display = 'none';
                    self.noteModalItemIndex = null;
                    self.noteModalText = '';
                    self.noteModalItemName = '';
                }, 150);
            } else {
                this.noteModalItemIndex = null;
                this.noteModalText = '';
                this.noteModalItemName = '';
            }
        },

        saveNote() {
            if (this.noteModalItemIndex === null) return;

            var item = this.cart[this.noteModalItemIndex];
            var noteText = this.noteModalText.trim() || null;

            if (item.quantity > 1) {
                // Split: original line keeps (qty-1), new noted line gets qty=1
                item.quantity--;
                var notedItem = Object.assign({}, item, { quantity: 1, note: noteText });
                notedItem.cart_key = item.menu_item_id + '|' + (item.variant_name || '') + '|' + (item.modifier_name || '') + '|' + (noteText || '');
                this.cart.splice(this.noteModalItemIndex + 1, 0, notedItem);
            } else {
                item.note = noteText;
                item.cart_key = item.menu_item_id + '|' + (item.variant_name || '') + '|' + (item.modifier_name || '') + '|' + (noteText || '');
            }

            this.closeNoteModal();
        },

        deleteNote(index) {
            var item = this.cart[index];
            item.note = null;
            var baseKey = item.menu_item_id + '|' + (item.variant_name || '') + '|' + (item.modifier_name || '') + '|';
            item.cart_key = baseKey;

            // Merge into existing non-noted line if one exists
            var self = this;
            var mergeIdx = null;
            this.cart.forEach(function(other, i) {
                if (i !== index && other.cart_key === baseKey && !other.note) mergeIdx = i;
            });
            if (mergeIdx !== null) {
                this.cart[mergeIdx].quantity += item.quantity;
                this.cart.splice(index, 1);
            }
        },

        selectCategory(id) {
            this.selectedCategoryId = id;
        }
    };
}
</script>
