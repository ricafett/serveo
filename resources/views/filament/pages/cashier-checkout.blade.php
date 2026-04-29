<x-filament-panels::page>
    @if (! $session)
        <div class="rounded-lg bg-warning-50 p-4 text-warning-900 dark:bg-warning-900 dark:text-warning-100">
            Não existe sessão de serviço aberta.
        </div>
    @elseif ($groups->isEmpty())
        <div class="rounded-lg bg-gray-50 p-4 text-gray-700 dark:bg-gray-800 dark:text-gray-300">Nenhum grupo a apresentar.</div>
    @else
        <div class="overflow-x-auto rounded-lg border bg-white dark:bg-gray-900">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-600 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left">Grupo</th>
                        <th class="px-3 py-2 text-left">Estado</th>
                        <th class="px-3 py-2 text-left">Zonas</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-right">Pago</th>
                        <th class="px-3 py-2 text-right">Saldo</th>
                        <th class="px-3 py-2 text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group)
                        <tr class="border-t {{ $group->is_closed ? 'opacity-60' : '' }}">
                            <td class="px-3 py-2">
                                <a class="font-semibold text-primary-600 hover:underline dark:text-primary-400"
                                   href="{{ \App\Filament\Pages\BillingGroupDetail::getUrl(['record' => $group->id]) }}">
                                    {{ $group->display_code }}
                                </a>
                            </td>
                            <td class="px-3 py-2">{{ $group->status?->display_name ?? $group->status?->code }}</td>
                            <td class="px-3 py-2 text-xs">
                                @foreach ($group->occupiedZones as $zone)
                                    <div>{{ $zone->row?->section?->section_code }} · {{ $zone->rangeLabel() }}</div>
                                @endforeach
                            </td>
                            <td class="px-3 py-2 text-right">{{ number_format($group->chargesTotal(), 2, ',', ' ') }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($group->paymentsTotal(), 2, ',', ' ') }}</td>
                            <td class="px-3 py-2 text-right font-semibold">
                                {{ number_format($group->balance(), 2, ',', ' ') }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <div class="flex flex-wrap justify-end gap-1">
                                    @if (! $group->is_closed)
                                        <x-filament::button size="xs" color="warning"
                                            wire:click="generateBill({{ $group->id }})">Imprimir conta</x-filament::button>
                                    @endif
                                    <x-filament::button size="xs" color="gray"
                                        wire:click="reprintLastBill({{ $group->id }})">Reimprimir</x-filament::button>
                                    @if ($group->is_closed)
                                        <x-filament::button size="xs" color="gray"
                                            wire:click="reopenGroup({{ $group->id }})"
                                            wire:confirm="Reabrir grupo {{ $group->display_code }}?">Reabrir</x-filament::button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament-panels::page>
