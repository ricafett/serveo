<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            @foreach ($categories as $category)
            <div class="rounded-lg border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-2 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400">
                    {{ $category->display_name }} <span class="text-xs">({{ $category->route_type }})</span>
                </h3>
                <div class="flex flex-wrap gap-2">
                    @foreach ($category->items as $item)
                        <button type="button" wire:click="addItem({{ $item->id }})"
                            class="rounded border border-primary-300 bg-primary-50 px-3 py-2 text-left text-sm hover:bg-primary-100 dark:border-primary-700 dark:bg-primary-900 dark:hover:bg-primary-800">
                            <div class="font-medium">{{ $item->display_name }}</div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">{{ number_format((float)$item->unit_price, 2, ',', ' ') }} EUR</div>
                        </button>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

        <div class="space-y-4">
            <div class="rounded-lg border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-2 text-sm font-semibold">Carrinho</h3>
                @if (empty($cartDetailed))
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum item adicionado.</p>
                @else
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach ($cartDetailed as $line)
                                <tr class="border-b last:border-0 dark:border-gray-700">
                                    <td class="py-1">
                                        <div class="font-medium">{{ $line['name'] }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($line['price'], 2, ',', ' ') }} EUR</div>
                                    </td>
                                    <td class="py-1 text-center">
                                        <button class="px-1" wire:click="changeQty({{ $line['index'] }}, -1)">−</button>
                                        <span class="px-1">{{ $line['qty'] }}</span>
                                        <button class="px-1" wire:click="changeQty({{ $line['index'] }}, 1)">+</button>
                                    </td>
                                    <td class="py-1 text-right">{{ number_format($line['subtotal'], 2, ',', ' ') }}</td>
                                    <td class="py-1 pl-2">
                                        <button wire:click="removeItem({{ $line['index'] }})"
                                            class="text-xs text-danger-600 dark:text-danger-400">remover</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold">
                                <td class="pt-2">Total</td>
                                <td></td>
                                <td class="pt-2 text-right">{{ number_format($total, 2, ',', ' ') }} EUR</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>

            <div class="rounded-lg border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Zona de entrega</label>
                <select wire:model.live="occupiedZoneId" class="mt-1 w-full rounded border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
                    <option value="">— sem zona específica —</option>
                    @foreach ($zoneOptions as $id => $label)
                        <option value="{{ $id }}">{{ $label }}</option>
                    @endforeach
                </select>

                @if (! empty($pairOptions))
                    <label class="mt-3 block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Par de lugar (opcional)</label>
                    <select wire:model.live="deliveryPairId" class="mt-1 w-full rounded border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800">
                        <option value="">— centro da zona —</option>
                        @foreach ($pairOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @endif

                <label class="mt-3 block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Notas</label>
                <textarea wire:model.defer="notes" rows="2"
                    class="mt-1 w-full rounded border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-800"></textarea>
            </div>

            <x-filament::button wire:click="submitOrder" color="primary" icon="heroicon-o-paper-airplane" class="w-full">
                Enviar pedido
            </x-filament::button>
            <a href="{{ \App\Filament\Pages\BillingGroupDetail::getUrl(['record' => $group->id]) }}"
               class="block text-center text-xs text-gray-500 hover:underline">Cancelar e voltar ao grupo</a>
        </div>
    </div>
</x-filament-panels::page>
