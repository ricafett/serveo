<div
    x-data="salesEntry(@js($menuItemsData), @js($menuCategoriesData), {{ $defaultCategoryId ?? 'null' }})"
    @sale-completed.window="cart = []; activeTab = 'menu'"
    class="p-4 sm:p-6 lg:p-8"
>
    <div class="max-w-5xl mx-auto">
        @if($errorMessage)
            <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 p-3 text-base text-red-600 dark:text-red-400">{{ $errorMessage }}</div>
        @endif
        @if($successMessage)
            <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-base text-green-600 dark:text-green-400">{{ $successMessage }}</div>
        @endif

        <div class="lg:hidden mb-4 flex rounded-lg bg-gray-100 dark:bg-gray-800 p-1">
            <button type="button" @click="activeTab = 'menu'" class="flex-1 rounded-md px-4 py-2.5 text-base font-medium min-h-[44px] transition-colors" :class="activeTab === 'menu' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400'">{{ __('sales.menu_tab') }}</button>
            <button type="button" @click="activeTab = 'cart'" class="flex-1 rounded-md px-4 py-2.5 text-base font-medium min-h-[44px] transition-colors" :class="activeTab === 'cart' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400'">{{ __('sales.cart_tab') }} <span x-show="cartItemCount > 0" x-text="'(' + cartItemCount + ')'" class="text-sm opacity-75"></span></button>
        </div>

        <div class="lg:flex lg:gap-6">
            <div :class="activeTab === 'menu' ? 'block' : 'hidden'" class="lg:block lg:flex-1 pb-20 sm:pb-6 lg:pb-0">
                <div class="mb-4 flex gap-2 overflow-x-auto pb-2 -mx-4 px-4 sm:-mx-6 sm:px-6 lg:-mx-0 lg:px-0">
                    <template x-for="category in menuCategories" :key="category.id">
                        <button type="button" @click="selectCategory(category.id)" class="shrink-0 rounded-lg px-4 py-2.5 text-base font-medium min-h-[44px] transition-colors" :class="selectedCategoryId === category.id ? 'bg-primary-600 text-white' : 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700'" x-text="category.display_name"></button>
                    </template>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    <template x-for="menuItem in filteredItems" :key="menuItem.id">
                        <button type="button" @click="addItem(menuItem)" class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 text-left hover:border-primary-300 dark:hover:border-primary-700 transition-colors min-h-[80px] flex flex-col justify-between">
                            <div>
                                <div class="text-base font-medium text-gray-900 dark:text-white leading-tight" x-text="menuItem.display_name"></div>
                            </div>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-base text-gray-500 dark:text-gray-400" x-text="menuItem.unit_price.toFixed(2) + ' €'"></span>
                                <template x-if="getItemTotalQuantity(menuItem.id) > 0">
                                    <span class="rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 min-w-[1.75rem] h-7 flex items-center justify-center text-base font-bold px-1.5" x-text="'×' + getItemTotalQuantity(menuItem.id)"></span>
                                </template>
                            </div>
                        </button>
                    </template>
                    <template x-if="filteredItems.length === 0">
                        <div class="col-span-full text-center py-8 text-base text-gray-500 dark:text-gray-400">{{ __('sales.no_items') }}</div>
                    </template>
                </div>
            </div>

            <div :class="activeTab === 'cart' ? 'block' : 'hidden'" class="lg:block lg:w-96 lg:shrink-0 pb-20 sm:pb-6 lg:pb-0">
                <div class="lg:sticky lg:top-4 space-y-4">
                    <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800">
                            <h2 class="font-semibold text-gray-900 dark:text-white">{{ __('sales.cart_title') }}</h2>
                        </div>
                        <div class="divide-y divide-gray-200 dark:divide-gray-800">
                            <template x-if="cart.length > 0">
                                <template x-for="(item, index) in cart" :key="item.cart_key">
                                    <div class="px-4 py-3 flex items-center justify-between">
                                        <div class="min-w-0 flex-1 flex items-center gap-2">
                                            <span class="text-base font-medium text-gray-900 dark:text-white truncate" x-text="item.display_name"></span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400 shrink-0" x-text="(item.unit_price * item.quantity).toFixed(2) + ' €'"></span>
                                        </div>
                                        <div class="flex items-center gap-1 ml-3">
                                            <button type="button" @click="decrement(index)" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center">-</button>
                                            <span class="text-base font-semibold text-gray-900 dark:text-white w-6 text-center" x-text="item.quantity"></span>
                                            <button type="button" @click="increment(index)" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center">+</button>
                                        </div>
                                    </div>
                                </template>
                            </template>
                            <template x-if="cart.length === 0">
                                <div class="px-4 py-8 text-center text-base text-gray-500 dark:text-gray-400">{{ __('sales.empty_cart') }}</div>
                            </template>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800">
                            <h2 class="font-semibold text-gray-900 dark:text-white">{{ __('sales.payment_title') }}</h2>
                        </div>
                        <div class="p-4 space-y-4">
                            <div>
                                <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('sales.amount') }}</label>
                                <input id="sale-payment-amount" type="number" wire:model="paymentAmount" step="0.01" min="0.01" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base h-11 px-3">
                            </div>
                            <div>
                                <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('sales.payment_method') }}</label>
                                <input id="sale-payment-label" type="text" wire:model="paymentLabel" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base h-11 px-3">
                            </div>
                            <div>
                                <label class="block text-base font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('app.notes') }}</label>
                                <textarea id="sale-payment-notes" wire:model="paymentNotes" rows="2" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-base p-3"></textarea>
                            </div>
                            <label class="inline-flex items-center gap-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-3 py-2 text-base text-gray-700 dark:text-gray-300 cursor-pointer min-h-[44px]">
                                <input id="sale-print-receipt" type="checkbox" wire:model="printReceipt" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500">
                                <span>{{ __('sales.print_receipt') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="sm:static sm:mt-4 sm:bg-transparent sm:border-0 sm:p-0 fixed bottom-14 left-0 right-0 px-4 py-3 bg-gray-50/95 dark:bg-gray-950/95 backdrop-blur border-t border-gray-200 dark:border-gray-800 z-30">
            <button
                type="button"
                @click="cart.length && $wire.call('completeSale', cart.map(function(i) { return { menu_item_id: i.menu_item_id, quantity: i.quantity }; }))"
                :disabled="cart.length === 0"
                wire:target="completeSale"
                wire:loading.attr="disabled"
                class="w-full flex justify-center items-center rounded-lg bg-primary-600 px-4 py-3 text-base font-semibold text-white shadow-sm hover:bg-primary-500 min-h-[48px] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {{ __('sales.pay_and_print') }}
                <template x-if="cartItemCount > 0">
                    <span class="ml-2 text-sm opacity-75" x-text="'(' + cartItemCount + ' · ' + cartTotal.toFixed(2) + ' €)'"></span>
                </template>
            </button>
        </div>
    </div>
</div>

<script>
    function salesEntry(menuItems, menuCategories, defaultCategoryId) {
        return {
            activeTab: 'menu',
            menuItems,
            menuCategories,
            selectedCategoryId: defaultCategoryId,
            cart: [],
            init() {
                this.$watch('cartTotal', value => {
                    if (value > 0) {
                        this.$wire.set('paymentAmount', value);
                    }
                });
            },
            get filteredItems() {
                if (! this.selectedCategoryId) {
                    return this.menuItems;
                }

                return this.menuItems.filter(item => item.category_id === this.selectedCategoryId);
            },
            selectCategory(categoryId) {
                this.selectedCategoryId = categoryId;
            },
            addItem(menuItem) {
                const existing = this.cart.find(item => item.menu_item_id === menuItem.id);
                if (existing) {
                    existing.quantity++;
                    return;
                }

                this.cart.push({
                    cart_key: `${menuItem.id}-${Date.now()}-${Math.random()}`,
                    menu_item_id: menuItem.id,
                    display_name: menuItem.display_name,
                    unit_price: menuItem.unit_price,
                    quantity: 1,
                });
            },
            increment(index) {
                this.cart[index].quantity++;
            },
            decrement(index) {
                if (this.cart[index].quantity <= 1) {
                    this.cart.splice(index, 1);
                    return;
                }

                this.cart[index].quantity--;
            },
            getItemTotalQuantity(menuItemId) {
                return this.cart.filter(item => item.menu_item_id === menuItemId).reduce((sum, item) => sum + item.quantity, 0);
            },
            get cartItemCount() {
                return this.cart.reduce((sum, item) => sum + item.quantity, 0);
            },
            get cartTotal() {
                return this.cart.reduce((sum, item) => sum + (item.unit_price * item.quantity), 0);
            },
        };
    }
</script>
