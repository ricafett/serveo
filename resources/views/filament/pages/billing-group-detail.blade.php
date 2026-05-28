<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing.status') }}</div>
            <div class="text-lg font-semibold">{{ $group->status?->display_name ?? $group->status?->code }}</div>
        </div>
        <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing.total_to_pay') }}</div>
            <div class="text-lg font-semibold">{{ number_format($charges, 2, ',', ' ') }} EUR</div>
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing.paid') }}: {{ number_format($paid, 2, ',', ' ') }} EUR</div>
        </div>
        <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('billing.open_balance') }}</div>
            <div class="text-2xl font-bold {{ $balance > 0 ? 'text-warning-600' : 'text-success-600' }}">
                {{ number_format($balance, 2, ',', ' ') }} EUR
            </div>
        </div>
    </div>

    <div class="mt-6 rounded-lg border bg-white p-4 dark:bg-gray-900">
        <h3 class="mb-3 text-lg font-semibold">{{ __('billing.occupied_zones') }}</h3>
        @if ($group->occupiedZones->isEmpty())
            <p class="text-base text-gray-500 dark:text-gray-400">{{ __('billing.no_zones') }}</p>
        @else
            <ul class="divide-y dark:divide-gray-700">
                @foreach ($group->occupiedZones as $zone)
                    <li class="flex items-center justify-between py-2 text-base">
                        <div>
                            <span class="font-medium">{{ $zone->rangeLabel() }}</span>
                            <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">{{ __('billing.delivery') }}: {{ $zone->defaultDeliveryLabel() }}</span>
                            @if (! $zone->is_open)
                                <span class="ml-2 rounded-full bg-gray-200 px-2 py-0.5 text-sm dark:bg-gray-700">{{ __('billing.released') }}</span>
                            @endif
                        </div>
                        @if ($zone->is_open && ! $group->is_closed)
                            <x-filament::button
                                size="xs"
                                color="gray"
                                wire:click="releaseZone({{ $zone->id }})"
                                wire:confirm="{{ __('billing.release_confirm') }} {{ $zone->rangeLabel() }}?">
                                {{ __('billing.release_zone') }}
                            </x-filament::button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-6 rounded-lg border bg-white p-4 dark:bg-gray-900">
        <h3 class="mb-3 text-lg font-semibold">{{ __('billing.orders') }}</h3>
        @if ($group->orderHeaders->isEmpty())
            <p class="text-base text-gray-500 dark:text-gray-400">{{ __('billing.no_orders') }}</p>
        @else
            @foreach ($group->orderHeaders as $header)
                <div class="mb-3 rounded border p-3 dark:border-gray-700">
                    <div class="mb-1 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                        <span>{{ __('order.title') }} #{{ $header->id }} · {{ $header->ordered_at?->timezone(config('app.timezone'))->format('H:i') }} · {{ $header->submission_status }}</span>
                    </div>
                    <table class="w-full text-base">
                        <thead>
                            <tr class="text-left text-sm uppercase text-gray-500 dark:text-gray-400">
                                <th class="py-1">{{ __('billing.item') }}</th>
                                <th class="py-1">{{ __('billing.qty') }}</th>
                                <th class="py-1">{{ __('billing.route') }}</th>
                                <th class="py-1">{{ __('billing.delivery') }}</th>
                                <th class="py-1 text-right">{{ __('billing.subtotal') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($header->items as $item)
                                <tr class="{{ $item->voided_at ? 'line-through text-gray-400 dark:text-gray-500' : '' }}">
                                    <td class="py-1">{{ $item->menuItem?->display_name }}</td>
                                    <td class="py-1">{{ $item->quantity }}</td>
                                    <td class="py-1">{{ $item->fulfillment_route }}</td>
                                    <td class="py-1">{{ $item->delivery_reference_label }}</td>
                                    <td class="py-1 text-right">{{ number_format((float)$item->line_subtotal, 2, ',', ' ') }} EUR</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </div>

    <div class="mt-6 rounded-lg border bg-white p-4 dark:bg-gray-900">
        <h3 class="mb-3 text-lg font-semibold">{{ __('billing.documents_payments') }}</h3>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <div class="mb-1 text-sm uppercase text-gray-500 dark:text-gray-400">{{ __('billing.printed_bills') }}</div>
                @forelse ($group->billingDocuments as $doc)
                    <div class="text-base">
                        {{ $doc->document_number }} · {{ $doc->document_type }} ·
                        {{ number_format((float)$doc->total_amount, 2, ',', ' ') }} EUR
                        @if ($doc->is_reprint) <em>({{ __('billing.reprint') }})</em> @endif
                    </div>
                @empty
                    <p class="text-base text-gray-500 dark:text-gray-400">{{ __('billing.no_bills') }}</p>
                @endforelse
            </div>
            <div>
                <div class="mb-1 text-sm uppercase text-gray-500 dark:text-gray-400">{{ __('billing.payments') }}</div>
                @forelse ($group->paymentRecords as $p)
                    <div class="text-base {{ $p->is_voided ? 'line-through text-gray-400 dark:text-gray-500' : '' }}">
                        {{ $p->payment_label }} · {{ number_format((float)$p->amount, 2, ',', ' ') }} EUR ·
                        {{ $p->recorded_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}
                    </div>
                @empty
                    <p class="text-base text-gray-500 dark:text-gray-400">{{ __('billing.no_payments') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
