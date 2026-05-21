<div
    x-data="orderEntry(@js($menuItemsData), @js($menuCategoriesData), {{ $defaultCategoryId }})"
    @order-submitted.window="cart = []"
    class="p-4 sm:p-6 lg:p-8"
>
    <div class="max-w-4xl mx-auto">
        {{-- Header --}}
        <div class="mb-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    wire:click="goBack"
                    class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" /></svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('order.order_entry') }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $this->group?->display_code }}</p>
                </div>
            </div>
            @if($this->group?->is_closed)
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('app.closed') }}</span>
            @endif
        </div>

        {{-- Messages --}}
        @if($errorMessage)
            <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-600 dark:text-red-400">
                {{ $errorMessage }}
            </div>
        @endif
        @if($successMessage)
            <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-sm text-green-600 dark:text-green-400">
                {{ $successMessage }}
            </div>
        @endif

        {{-- Tab Bar (mobile only) --}}
        <div class="lg:hidden mb-4 flex rounded-lg bg-gray-100 dark:bg-gray-800 p-1">
            <button
                type="button"
                @click="activeTab = 'menu'"
                class="flex-1 rounded-md px-4 py-2.5 text-sm font-medium min-h-[44px] transition-colors"
                :class="activeTab === 'menu' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400'"
            >
                {{ __('order.menu_tab') }}
            </button>
            <button
                type="button"
                @click="activeTab = 'order'"
                class="flex-1 rounded-md px-4 py-2.5 text-sm font-medium min-h-[44px] transition-colors flex items-center justify-center gap-1.5"
                :class="activeTab === 'order' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400'"
            >
                {{ __('order.order_tab') }}
                <span
                    x-show="cartItemCount > 0"
                    x-text="'(' + cartItemCount + ')'"
                    class="text-xs opacity-75"
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
                                class="shrink-0 rounded-lg px-4 py-2.5 text-sm font-medium min-h-[44px] transition-colors"
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
                            @click="addToCart(menuItem.id)"
                            class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 text-left hover:border-primary-300 dark:hover:border-primary-700 transition-colors min-h-[80px] flex flex-col justify-between"
                        >
                            <div class="text-sm font-medium text-gray-900 dark:text-white leading-tight" x-text="menuItem.display_name"></div>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    <template x-if="getItemQuantity(menuItem.id) > 0">
                                        <span x-text="(menuItem.unit_price * getItemQuantity(menuItem.id)).toFixed(2)"></span>
                                    </template>
                                    <template x-if="getItemQuantity(menuItem.id) === 0">
                                        <span x-text="menuItem.unit_price.toFixed(2)"></span>
                                    </template>
                                </span>
                                <template x-if="getItemQuantity(menuItem.id) > 0">
                                    <span
                                        class="rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 min-w-[1.75rem] h-7 flex items-center justify-center text-sm font-bold px-1.5"
                                        x-text="'×' + getItemQuantity(menuItem.id)"
                                    ></span>
                                </template>
                            </div>
                        </button>
                    </template>
                    <template x-if="filteredItems.length === 0">
                        <div class="col-span-full text-center py-8 text-sm text-gray-500 dark:text-gray-400">
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
                                class="w-full px-4 py-3 flex items-center justify-between text-sm font-medium text-gray-900 dark:text-white hover:bg-gray-50 dark:hover:bg-gray-800/50 min-h-[44px]"
                            >
                                <span>{{ __('order.delivery') }}</span>
                                <div class="flex items-center gap-2">
                                    {{-- Collapsed summary --}}
                                    <span x-show="!deliveryOpen" class="text-xs font-normal text-gray-500 dark:text-gray-400 truncate max-w-[180px]">
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
                                <div class="px-4 pt-1 pb-4 space-y-3">
                                    {{-- Zone Selector --}}
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">{{ __('order.delivery_zone') }}</label>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            wire:click="setZone(null)"
                                            class="rounded-lg px-3 py-2 text-sm font-medium min-h-[44px] transition-colors {{ $selectedZoneId === null ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 ring-1 ring-primary-300 dark:ring-primary-700' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                                        >
                                            {{ __('order.group_level') }}
                                        </button>
                                        @foreach($this->zones as $zone)
                                            <button
                                                type="button"
                                                wire:click="setZone({{ $zone->id }})"
                                                class="rounded-lg px-3 py-2 text-sm font-medium min-h-[44px] transition-colors {{ $selectedZoneId === $zone->id ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 ring-1 ring-primary-300 dark:ring-primary-700' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                                            >
                                                {{ $zone->rangeLabel() }}
                                            </button>
                                        @endforeach
                                    </div>

                                    {{-- Delivery Pair Override --}}
                                    @if($this->selectedZone)
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mt-3 mb-2">{{ __('order.seat_pair') }}</label>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                wire:click="setDeliveryPair(null)"
                                                class="rounded-lg px-3 py-2 text-sm font-medium min-h-[44px] transition-colors {{ $selectedDeliveryPairId === null ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 ring-1 ring-primary-300 dark:ring-primary-700' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
                                            >
                                                {{ __('order.center') }}
                                            </button>
                                            @foreach($this->selectedZone->row?->seatPairs ?? [] as $pair)
                                                @if($pair->pair_sequence >= $this->selectedZone->start_seat_pair_sequence && $pair->pair_sequence <= $this->selectedZone->end_seat_pair_sequence)
                                                    <button
                                                        type="button"
                                                        wire:click="setDeliveryPair({{ $pair->id }})"
                                                        class="rounded-lg px-3 py-2 text-sm font-medium min-h-[44px] transition-colors {{ $selectedDeliveryPairId === $pair->id ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 ring-1 ring-primary-300 dark:ring-primary-700' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}"
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
                                    <template x-for="(item, index) in cart" :key="item.menu_item_id">
                                        <div class="px-4 py-3 flex items-center justify-between">
                                            <div class="min-w-0 flex-1">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate" x-text="item.display_name"></div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    <span x-text="item.unit_price.toFixed(2)"></span>
                                                    <span x-show="item.quantity > 1" class="ml-1 text-gray-400 dark:text-gray-500">→</span>
                                                    <span x-show="item.quantity > 1" class="ml-1 font-medium text-gray-700 dark:text-gray-300" x-text="(item.unit_price * item.quantity).toFixed(2)"></span>
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
                                                <span class="text-sm font-semibold text-gray-900 dark:text-white w-6 text-center" x-text="item.quantity"></span>
                                                <button
                                                    type="button"
                                                    @click="increment(index)"
                                                    class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center"
                                                >
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <template x-if="cart.length === 0">
                            <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                {{ __('order.empty_cart') }}
                            </div>
                        </template>
                    </div>

                    {{-- Notes --}}
                    <div class="mb-0">
                        <label for="order-notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('app.notes') }}</label>
                        <textarea id="order-notes" wire:model="notes" rows="2" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm p-3"></textarea>
                    </div>

                </div>
            </div>
        </div>

        {{-- Submit button: fixed above nav on mobile, inline on desktop --}}
        <div class="sm:static sm:mt-4 sm:bg-transparent sm:border-0 sm:p-0 fixed bottom-14 left-0 right-0 px-4 py-3 bg-gray-50/95 dark:bg-gray-950/95 backdrop-blur border-t border-gray-200 dark:border-gray-800 z-30">
            <button
                type="button"
                @click="cart.length && $wire.call('submitOrder', cart.map(function(i) { return { menu_item_id: i.menu_item_id, quantity: i.quantity }; }))"
                :disabled="{{ $this->group?->is_closed ? 'true' : 'false' }} || cart.length === 0"
                class="w-full flex justify-center items-center rounded-lg bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 min-h-[48px] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {{ __('order.submit') }}
                <template x-if="cartItemCount > 0">
                    <span class="ml-2 text-xs opacity-75" x-text="'(' + cartItemCount + ' · ' + cartTotal.toFixed(2) + ')'"></span>
                </template>
            </button>
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

        getItemQuantity(menuItemId) {
            var item = this.cart.find(function (i) { return i.menu_item_id === menuItemId; });
            return item ? item.quantity : 0;
        },

        addToCart(menuItemId) {
            var menuItem = this.menuItems.find(function (i) { return i.id === menuItemId; });
            if (!menuItem) return;

            var existing = this.cart.find(function (i) { return i.menu_item_id === menuItemId; });
            if (existing) {
                existing.quantity++;
            } else {
                this.cart.push({
                    menu_item_id: menuItem.id,
                    display_name: menuItem.display_name,
                    unit_price: menuItem.unit_price,
                    quantity: 1,
                    route_type: menuItem.route_type
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

        selectCategory(id) {
            this.selectedCategoryId = id;
        }
    };
}
</script>
