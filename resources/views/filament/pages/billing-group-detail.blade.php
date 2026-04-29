<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
            <div class="text-xs text-gray-500">Estado</div>
            <div class="text-lg font-semibold">{{ $group->status?->display_name ?? $group->status?->code }}</div>
        </div>
        <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
            <div class="text-xs text-gray-500">Total a pagar</div>
            <div class="text-lg font-semibold">{{ number_format($charges, 2, ',', ' ') }} EUR</div>
            <div class="text-xs text-gray-500">Pago: {{ number_format($paid, 2, ',', ' ') }} EUR</div>
        </div>
        <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
            <div class="text-xs text-gray-500">Saldo em aberto</div>
            <div class="text-2xl font-bold {{ $balance > 0 ? 'text-warning-600' : 'text-success-600' }}">
                {{ number_format($balance, 2, ',', ' ') }} EUR
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-lg border bg-white p-4 dark:bg-gray-900">
        <h3 class="mb-3 text-base font-semibold">Zonas ocupadas</h3>
        @if ($group->occupiedZones->isEmpty())
            <p class="text-sm text-gray-500">Sem zonas atribuídas.</p>
        @else
            <ul class="divide-y">
                @foreach ($group->occupiedZones as $zone)
                    <li class="flex items-center justify-between py-2 text-sm">
                        <div>
                            <span class="font-medium">{{ $zone->row?->section?->section_code }} · {{ $zone->rangeLabel() }}</span>
                            <span class="ml-2 text-xs text-gray-500">Entrega: {{ $zone->defaultDeliveryLabel() }}</span>
                            @if (! $zone->is_open)
                                <span class="ml-2 rounded-full bg-gray-200 px-2 py-0.5 text-xs">libertada</span>
                            @endif
                        </div>
                        @if ($zone->is_open && ! $group->is_closed)
                            <x-filament::button
                                size="xs"
                                color="gray"
                                wire:click="releaseZone({{ $zone->id }})"
                                wire:confirm="Libertar zona {{ $zone->rangeLabel() }}?">
                                Libertar
                            </x-filament::button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-6 rounded-lg border bg-white p-4 dark:bg-gray-900">
        <h3 class="mb-3 text-base font-semibold">Pedidos</h3>
        @if ($group->orderHeaders->isEmpty())
            <p class="text-sm text-gray-500">Sem pedidos.</p>
        @else
            @foreach ($group->orderHeaders as $header)
                <div class="mb-3 rounded border p-3">
                    <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
                        <span>Pedido #{{ $header->id }} · {{ $header->ordered_at?->format('H:i') }} · {{ $header->submission_status }}</span>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-1">Item</th>
                                <th class="py-1">Qtd</th>
                                <th class="py-1">Rota</th>
                                <th class="py-1">Entrega</th>
                                <th class="py-1 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($header->items as $item)
                                <tr class="{{ $item->voided_at ? 'line-through text-gray-400' : '' }}">
                                    <td class="py-1">{{ $item->menuItem?->display_name }}</td>
                                    <td class="py-1">{{ $item->quantity }}</td>
                                    <td class="py-1">{{ $item->fulfillment_route }}</td>
                                    <td class="py-1">{{ $item->delivery_reference_label }}</td>
                                    <td class="py-1 text-right">{{ number_format((float)$item->line_subtotal, 2, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </div>

    <div class="mt-6 rounded-lg border bg-white p-4 dark:bg-gray-900">
        <h3 class="mb-3 text-base font-semibold">Documentos &amp; pagamentos</h3>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <div class="mb-1 text-xs uppercase text-gray-500">Contas impressas</div>
                @forelse ($group->billingDocuments as $doc)
                    <div class="text-sm">
                        {{ $doc->document_number }} · {{ $doc->document_type }} ·
                        {{ number_format((float)$doc->total_amount, 2, ',', ' ') }} EUR
                        @if ($doc->is_reprint) <em>(reimpressão)</em> @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Sem contas impressas.</p>
                @endforelse
            </div>
            <div>
                <div class="mb-1 text-xs uppercase text-gray-500">Pagamentos</div>
                @forelse ($group->paymentRecords as $p)
                    <div class="text-sm {{ $p->is_voided ? 'line-through text-gray-400' : '' }}">
                        {{ $p->payment_label }} · {{ number_format((float)$p->amount, 2, ',', ' ') }} EUR ·
                        {{ $p->recorded_at?->format('Y-m-d H:i') }}
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Sem pagamentos.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
