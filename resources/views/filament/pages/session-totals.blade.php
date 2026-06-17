<x-filament-panels::page>
    @if (! $this->session)
        <div class="rounded-lg border bg-white p-8 text-center dark:bg-gray-900">
            <p class="text-lg text-gray-500 dark:text-gray-400">{{ __('app.no_open_session') }}</p>
        </div>
    @else
        {{-- Session info bar --}}
        <div class="mb-6 rounded-lg border bg-white p-4 dark:bg-gray-900">
            <div class="flex flex-wrap items-center gap-4">
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.session') }}:</span>
                    <span class="ml-1 font-semibold">{{ $this->session->session_label }}</span>
                </div>
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.status') }}:</span>
                    <span class="ml-1 inline-flex items-center rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-medium text-success-800 dark:bg-success-900 dark:text-success-200">
                        {{ $this->session->status }}
                    </span>
                </div>
                <div>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.started_at') }}:</span>
                    <span class="ml-1">{{ $this->session->starts_at?->format('Y-m-d H:i') }}</span>
                </div>
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.total_cash_in') }}</div>
                <div class="text-xl font-bold text-success-600">{{ number_format($this->summary['cash_in'] ?? 0, 2, ',', ' ') }} €</div>
            </div>
            <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.total_cash_out') }}</div>
                <div class="text-xl font-bold text-danger-600">{{ number_format($this->summary['cash_out'] ?? 0, 2, ',', ' ') }} €</div>
            </div>
            <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.total_bill_payments') }}</div>
                <div class="text-xl font-bold text-primary-600">{{ number_format($this->summary['bill_payments'] ?? 0, 2, ',', ' ') }} €</div>
            </div>
            <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.total_sale_payments') }}</div>
                <div class="text-xl font-bold text-primary-600">{{ number_format($this->summary['sale_payments'] ?? 0, 2, ',', ' ') }} €</div>
            </div>
        </div>

        {{-- Extra summary row --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.net_cash_movement') }}</div>
                <div class="text-lg font-semibold">{{ number_format($this->summary['net_cash_movement'] ?? 0, 2, ',', ' ') }} €</div>
            </div>
            <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.total_payments') }}</div>
                <div class="text-lg font-semibold">{{ number_format($this->summary['total_payments'] ?? 0, 2, ',', ' ') }} €</div>
            </div>
            <div class="rounded-lg border bg-white p-4 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.overall_balance') }}</div>
                <div class="text-lg font-semibold {{ ($this->summary['overall_balance'] ?? 0) >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                    {{ number_format($this->summary['overall_balance'] ?? 0, 2, ',', ' ') }} €
                </div>
            </div>
        </div>

        {{-- Per-cashier breakdown --}}
        <div class="mb-6 rounded-lg border bg-white dark:bg-gray-900">
            <div class="border-b px-4 py-3 dark:border-gray-700">
                <h3 class="text-lg font-semibold">{{ __('app.per_cashier_breakdown') }}</h3>
            </div>
            @if ($this->cashiers->isEmpty())
                <div class="p-4 text-gray-500 dark:text-gray-400">{{ __('app.no_data') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('app.user') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('cashdrawer.cash_in') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('cashdrawer.cash_out') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('app.bill_payments_column') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('app.sale_payments_column') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('app.net') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-gray-700">
                            @foreach ($this->cashiers as $cashier)
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $cashier['user_name'] }}</td>
                                    <td class="px-4 py-2 text-success-600">{{ number_format($cashier['cash_in'], 2, ',', ' ') }} €</td>
                                    <td class="px-4 py-2 text-danger-600">{{ number_format($cashier['cash_out'], 2, ',', ' ') }} €</td>
                                    <td class="px-4 py-2">{{ number_format($cashier['bill_payments'], 2, ',', ' ') }} €</td>
                                    <td class="px-4 py-2">{{ number_format($cashier['sale_payments'], 2, ',', ' ') }} €</td>
                                    <td class="px-4 py-2 font-semibold {{ $cashier['net'] >= 0 ? 'text-success-600' : 'text-danger-600' }}">
                                        {{ number_format($cashier['net'], 2, ',', ' ') }} €
                                    </td>
                                </tr>
                            @endforeach
                            {{-- Totals row --}}
                            <tr class="bg-gray-50 font-semibold dark:bg-gray-800">
                                <td class="px-4 py-2">{{ __('app.totals') }}</td>
                                <td class="px-4 py-2">{{ number_format($this->summary['cash_in'] ?? 0, 2, ',', ' ') }} €</td>
                                <td class="px-4 py-2">{{ number_format($this->summary['cash_out'] ?? 0, 2, ',', ' ') }} €</td>
                                <td class="px-4 py-2">{{ number_format($this->summary['bill_payments'] ?? 0, 2, ',', ' ') }} €</td>
                                <td class="px-4 py-2">{{ number_format($this->summary['sale_payments'] ?? 0, 2, ',', ' ') }} €</td>
                                <td class="px-4 py-2">{{ number_format($this->summary['overall_balance'] ?? 0, 2, ',', ' ') }} €</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Inventory movements --}}
        <div class="mb-6 rounded-lg border bg-white dark:bg-gray-900">
            <div class="border-b px-4 py-3 dark:border-gray-700">
                <h3 class="text-lg font-semibold">{{ __('app.inventory_movements') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('app.inventory_movements_desc') }}</p>
            </div>
            @if ($this->inventory->isEmpty())
                <div class="p-4 text-gray-500 dark:text-gray-400">{{ __('app.no_data') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 w-12 font-medium">#</th>
                                <th class="px-4 py-2 font-medium">{{ __('app.item') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('app.variant') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('app.quantity_sold') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-gray-700">
                            @foreach ($this->inventory as $index => $item)
                                <tr>
                                    <td class="px-4 py-2 text-gray-500">{{ $index + 1 }}</td>
                                    <td class="px-4 py-2 font-medium">{{ $item['menu_item_name'] }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $item['variant_name'] ?: '—' }}</td>
                                    <td class="px-4 py-2 font-semibold">{{ $item['total_qty'] }}</td>
                                </tr>
                            @endforeach
                            <tr class="bg-gray-50 font-semibold dark:bg-gray-800">
                                <td class="px-4 py-2" colspan="3">{{ __('app.total_units_sold') }}</td>
                                <td class="px-4 py-2">{{ $this->inventory->sum('total_qty') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Recent printed documents --}}
        <div class="rounded-lg border bg-white dark:bg-gray-900">
            <div class="border-b px-4 py-3 dark:border-gray-700">
                <h3 class="text-lg font-semibold">{{ __('app.recent_session_documents') }}</h3>
            </div>
            @if ($this->recentDocuments->isEmpty())
                <div class="p-4 text-gray-500 dark:text-gray-400">{{ __('app.no_documents_yet') }}</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b bg-gray-50 dark:border-gray-700 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2 font-medium">{{ __('app.type') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('app.printer') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('app.status') }}</th>
                                <th class="px-4 py-2 font-medium">{{ __('app.created') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y dark:divide-gray-700">
                            @foreach ($this->recentDocuments as $doc)
                                <tr>
                                    <td class="px-4 py-2">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' => $doc->job_kind === 'SESSION_TOTALS',
                                            'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' => $doc->job_kind === 'INVENTORY_MOVEMENTS',
                                        ])>
                                            {{ $doc->job_kind === 'SESSION_TOTALS' ? __('app.document_type_session_totals') : __('app.document_type_inventory_movements') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $doc->printer?->name ?? '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span @class([
                                            'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200' => $doc->status === 'PRINTED',
                                            'bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-200' => $doc->status === 'FAILED',
                                            'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' => $doc->status === 'PENDING',
                                        ])>
                                            {{ $doc->status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $doc->created_at->format('Y-m-d H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
