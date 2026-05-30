<div class="max-w-4xl mx-auto p-4 sm:p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('menu.title') }}</h1>
        <p class="mt-1 text-base text-gray-500 dark:text-gray-400">{{ __('menu.subtitle') }}</p>
    </div>

    @if($categories->isEmpty())
        <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-8 text-center">
            <p class="text-gray-500 dark:text-gray-400">{{ __('menu.no_categories') }}</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($categories as $category)
                <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $category->display_name }}
                        </h2>
                        @if($category->route_type)
                            <span class="inline-flex items-center mt-1 rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $category->route_type === 'BAR' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' }}">
                                {{ $category->route_type === 'BAR' ? __('menu.route_bar') : __('menu.route_kitchen') }}
                            </span>
                        @endif
                    </div>

                    <div class="p-4">
                        @if($category->items->isEmpty())
                            <p class="text-gray-500 dark:text-gray-400 text-base">{{ __('menu.no_items') }}</p>
                        @else
                            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($category->items as $item)
                                    <div class="py-3 first:pt-0 last:pb-0">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1 min-w-0">
                                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                                    {{ $item->display_name }}
                                                </h3>
                                                @if($item->short_name)
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $item->short_name }}</p>
                                                @endif

                                                {{-- Variants --}}
                                                @if($item->activeVariants->isNotEmpty())
                                                    <div class="mt-1.5">
                                                        <span class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide">{{ __('menu.variants') }}</span>
                                                        <div class="flex flex-wrap gap-1 mt-0.5">
                                                            @foreach($item->activeVariants as $variant)
                                                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                                    {{ $variant->display_name }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                {{-- Modifier Set --}}
                                                @if($item->modifierSet && $item->modifierSet->items->isNotEmpty())
                                                    <div class="mt-1.5">
                                                        <span class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wide">
                                                            {{ __('menu.modifiers') }}: {{ $item->modifierSet->display_name }}
                                                        </span>
                                                        <div class="flex flex-wrap gap-1 mt-0.5">
                                                            @foreach($item->modifierSet->items as $modifier)
                                                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                                                    {{ $modifier->display_name }}
                                                                    @if($item->modifierSet->defaultItem && $item->modifierSet->defaultItem->id === $modifier->id)
                                                                        <span class="ml-1 opacity-70">({{ __('menu.default') }})</span>
                                                                    @endif
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="ml-4 flex-shrink-0 text-right">
                                                <span class="text-base font-bold text-gray-900 dark:text-white">
                                                    {{ number_format($item->unit_price, 2) }} €
                                                </span>
                                                @if($item->sku)
                                                    <p class="text-xs text-gray-400 dark:text-gray-500">{{ $item->sku }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
