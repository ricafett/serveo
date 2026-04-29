<x-filament-panels::page>
    @if (! $session)
        <div class="rounded-lg bg-warning-50 p-4 text-warning-900 dark:bg-warning-900 dark:text-warning-100">
            Não existe nenhuma sessão de serviço aberta. Crie uma sessão em
            <em>Configuração &rarr; Sessões de serviço</em> antes de operar o salão.
        </div>
    @else
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold">{{ $session->session_type }} &mdash; {{ $session->session_label }}</h2>
                <p class="text-sm text-gray-500">Início: {{ $session->starts_at?->format('Y-m-d H:i') }}</p>
            </div>
            <x-filament::button wire:click="openGroup" icon="heroicon-o-plus">
                Abrir novo grupo
            </x-filament::button>
        </div>

        <div class="space-y-6">
            @foreach ($sections as $section)
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:bg-gray-900">
                    <h3 class="mb-3 text-lg font-semibold">{{ $section->name }} ({{ $section->section_code }})</h3>
                    <div class="space-y-3">
                        @foreach ($section->rows as $row)
                            <div>
                                <div class="mb-1 flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Fila {{ $row->row_code }}
                                </div>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($row->seatPairs as $pair)
                                        @php
                                            $occupied = $row->occupiedZones->first(fn ($z) =>
                                                $pair->pair_sequence >= $z->start_seat_pair_sequence &&
                                                $pair->pair_sequence <= $z->end_seat_pair_sequence
                                            );
                                            $color = $occupied ? 'bg-primary-100 border-primary-400 text-primary-900' : 'bg-gray-50 border-gray-200 text-gray-700';
                                        @endphp
                                        <div class="w-14 rounded-md border px-2 py-1 text-center text-xs {{ $color }}">
                                            <div class="font-semibold">P{{ $pair->pair_sequence }}</div>
                                            <div class="text-[10px]">
                                                {{ $occupied?->billingGroup?->display_code ?? '—' }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-8">
            <h3 class="mb-3 text-lg font-semibold">Grupos abertos</h3>
            @if ($openGroups->isEmpty())
                <p class="text-sm text-gray-500">Nenhum grupo aberto nesta sessão.</p>
            @else
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($openGroups as $group)
                        <a href="{{ \App\Filament\Pages\BillingGroupDetail::getUrl(['record' => $group->id]) }}"
                           class="block rounded-lg border border-gray-200 p-4 transition hover:border-primary-400 hover:shadow dark:bg-gray-900">
                            <div class="flex items-center justify-between">
                                <div class="text-base font-semibold">{{ $group->display_code }}</div>
                                <span class="rounded-full bg-primary-50 px-2 py-0.5 text-xs text-primary-700">
                                    {{ $group->status?->display_name ?? $group->status?->code }}
                                </span>
                            </div>
                            <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                                @forelse ($group->occupiedZones as $zone)
                                    <div>{{ $zone->row?->section?->section_code }} · {{ $zone->rangeLabel() }}</div>
                                @empty
                                    <em>Sem zonas atribuídas</em>
                                @endforelse
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
