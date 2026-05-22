<div class="p-4 sm:p-6 lg:p-8" x-data="{ createModalOpen: @entangle('showCreateModal') }" wire:poll.15s>
    <div class="max-w-7xl mx-auto">
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('floor.title') }}</h1>
        </div>

        {{-- Filter Toggles (independent, combinable) --}}
        <div class="mb-4 flex gap-2">
            <button wire:click="$toggle('favoritesOnly')"
                class="flex-1 flex items-center justify-center gap-2 min-h-[48px] px-4 py-2.5 text-base rounded-xl font-medium transition-colors
                    {{ $favoritesOnly
                        ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 ring-1 ring-primary-300 dark:ring-primary-700'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                <span class="text-lg">★</span> {{ __('floor.filter_favorites') }}
            </button>
            <button wire:click="$toggle('showFreeSeats')"
                class="flex-1 flex items-center justify-center gap-2 min-h-[48px] px-4 py-2.5 text-base rounded-xl font-medium transition-colors
                    {{ $showFreeSeats
                        ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300 ring-1 ring-primary-300 dark:ring-primary-700'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                {{ __('floor.filter_free_seats') }}
            </button>
        </div>

        {{-- Sections & Rows --}}
        <div class="space-y-6">
            @php
                $visibleSections = $this->sections->filter(fn($s) => $s->rows->contains(fn($r) => $this->rowHasVisibleRanges($r)));
            @endphp
            @forelse($visibleSections as $section)
                <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                    {{-- Section Header --}}
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-900 dark:text-white">{{ $section->name }}</h2>
                    </div>

                    {{-- Rows --}}
                    <div class="p-4 space-y-4">
                        @foreach($section->rows as $row)
                            @continue(!$this->rowHasVisibleRanges($row))
                            <div>
                                {{-- Row Label --}}
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
                                        {{ __('floor.row') }} {{ $row->row_code }}
                                    </span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $row->seatPairs->count() }} {{ __('app.pairs') }}
                                    </span>
                                </div>

                                {{-- Seat Pair Buttons --}}
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($this->getRowDisplayItems($row) as $item)
                                        {{-- Free pair: individual button --}}
                                        @if($item['type'] === 'free')
                                            @if($showFreeSeats && (!$favoritesOnly || ($item['default_server_id'] ?? null) === auth()->id()))
                                                <button
                                                    type="button"
                                                    wire:click="selectPair({{ $row->id }}, {{ $item['start'] }})"
                                                    class="rounded-lg px-3 py-2 text-sm font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:hover:bg-emerald-900/30 transition-colors min-h-[44px] flex items-center"
                                                    title="{{ __('floor.tap_to_open') }}"
                                                >
                                                    {{ $section->section_code }}{{ $row->row_code }}{{ str_pad((string) $item['start'], 2, '0', STR_PAD_LEFT) }}
                                                </button>
                                            @endif
                                        {{-- Occupied range: two-line layout --}}
                                        @elseif(!$favoritesOnly || ($item['group'] && in_array($item['group']->id, $this->favoriteGroupIds)))
                                            <button
                                                type="button"
                                                wire:click="openExistingGroup({{ $item['group']->id ?? 0 }})"
                                                class="rounded-lg px-3 py-2 text-sm font-medium bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30 transition-colors min-h-[44px] flex flex-col items-center justify-center"
                                                title="{{ $item['group']->name ?? $item['group']->display_code ?? '' }} — {{ $item['group']->status?->display_name ?? $item['group']->status?->code ?? '' }}"
                                            >
                                                @php
                                                    $startLabel = $section->section_code . $row->row_code . str_pad((string) $item['start'], 2, '0', STR_PAD_LEFT);
                                                    $endLabel = $section->section_code . $row->row_code . str_pad((string) $item['end'], 2, '0', STR_PAD_LEFT);
                                                @endphp
                                                <span class="text-xs leading-tight">{{ $item['start'] === $item['end'] ? $startLabel : $startLabel . '–' . $endLabel }}</span>
                                                <span class="text-[10px] leading-tight opacity-75">{{ \Illuminate\Support\Str::limit($item['group']->name ?? $item['group']->display_code, 20) }}</span>
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-8 text-center">
                    <p class="text-gray-500 dark:text-gray-400">{{ __('floor.no_sections') }}</p>
                </div>
            @endforelse
        </div>

        {{-- Open Groups Quick List --}}
        @if($this->openGroups->count() > 0)
            <div class="mt-8">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                    {{ __('floor.open_groups') }}
                    <span class="ml-2 inline-flex items-center justify-center min-w-[28px] h-7 px-2 text-sm font-bold text-indigo-700 dark:text-indigo-300 bg-indigo-100 dark:bg-indigo-900/30 rounded-full">
                        {{ $this->openGroups->count() }}
                    </span>
                </h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->openGroups as $group)
                        <div class="rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                            <a
                                href="{{ route('billing-groups.detail', ['id' => $group->id]) }}"
                                wire:navigate
                                class="block p-4 hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $group->name ?? $group->display_code }}</span>
                                    <span class="inline-flex items-center gap-1">
                                        @php
                                            $favPivot = $group->favoritedBy->where('id', Auth::id())->first();
                                            $isFavorited = (bool) $favPivot;
                                        @endphp
                                        @if($isFavorited)
                                            <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                            </svg>
                                        @endif
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                            {{ $group->status?->code === 'ACTIVE' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                            {{ $group->status?->code === 'WAITING' ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400' : '' }}
                                            {{ $group->status?->code === 'CHECK_REQUESTED' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                                        ">
                                            {{ $group->status?->display_name ?? $group->status?->code }}
                                        </span>
                                    </span>
                                </div>
                                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    @foreach($group->occupiedZones as $zone)
                                        <div>
                                            {{ $zone->rangeLabel() }}
                                            @if($zone->server)
                                                <span class="text-xs text-blue-600 dark:text-blue-400">({{ $zone->server->name }})</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                @if($group->cover_count)
                                    <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $group->cover_count }} {{ __('app.covers') }}</div>
                                @endif
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Create Billing Group Modal --}}
    <div
        x-show="createModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div
            x-show="createModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-black/50 dark:bg-black/70"
            @click="createModalOpen = false; $wire.closeModal()"
        ></div>

        {{-- Modal Panel --}}
        <div
            x-show="createModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="transform translate-y-full sm:translate-y-4 sm:scale-95 opacity-0"
            x-transition:enter-end="transform translate-y-0 sm:scale-100 opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="transform translate-y-0 sm:scale-100 opacity-100"
            x-transition:leave-end="transform translate-y-full sm:translate-y-4 sm:scale-95 opacity-0"
            class="relative w-full sm:w-[28rem] max-w-lg bg-white dark:bg-gray-900 rounded-t-2xl sm:rounded-2xl shadow-xl max-h-[90vh] overflow-y-auto"
        >
            <div class="p-4 sm:p-6">
                {{-- Header --}}
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('floor.open_group') }}</h2>
                    <button
                        type="button"
                        @click="createModalOpen = false; $wire.closeModal()"
                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 min-h-[44px] min-w-[44px] flex items-center justify-center"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Error --}}
                @if($errorMessage)
                    <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-600 dark:text-red-400">
                        {{ $errorMessage }}
                    </div>
                @endif

                {{-- Starting pair --}}
                @if($zoneRowId && $zoneStartSeq)
                    @php
                        $selRow = \App\Models\Row::with('section')->find($zoneRowId);
                        $startLabel = ($selRow?->section?->section_code ?? '') . ($selRow?->row_code ?? '') . str_pad((string) $zoneStartSeq, 2, '0', STR_PAD_LEFT);
                    @endphp
                    <div class="mb-4 rounded-lg bg-primary-50 dark:bg-primary-900/20 p-3 text-sm text-primary-700 dark:text-primary-300">
                        <span class="font-medium">{{ __('floor.starting_pair') }}:</span> {{ $startLabel }}
                    </div>
                @endif

                {{-- Form --}}
                <form wire:submit.prevent="createBillingGroup" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('billing.name') }}</label>
                        <input id="name" type="text" wire:model="name" required maxlength="255" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 px-3">
                        @error('name') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('app.status') }}</label>
                        <select id="status-code" wire:model="statusCode" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 px-3">
                            @foreach(\App\Models\BillingStatus::where('is_active', true)->orderBy('sort_order')->get() as $status)
                                <option value="{{ $status->code }}">{{ $status->display_name ?? $status->code }}</option>
                            @endforeach
                        </select>
                        @error('statusCode') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    {{-- Zone span: two cross-calculating fields --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('floor.number_of_seats') }}</label>
                            <input id="zone-seat-count" type="number" wire:model.live="zoneSeatCount" min="1" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 px-3">
                            @error('zoneSeatCount') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('floor.end_pair') }}</label>
                            <input id="zone-end-label" type="text" wire:model.live="zoneEndLabel" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 px-3">
                        </div>
                    </div>

                    {{-- Zone preview --}}
                    @if($zoneStartSeq && $zoneEndSeq && $zoneEndSeq >= $zoneStartSeq && $zoneRowId)
                        @php
                            $prevRow = \App\Models\Row::with('section')->find($zoneRowId);
                            $previewStart = ($prevRow?->section?->section_code ?? '') . ($prevRow?->row_code ?? '') . str_pad((string) $zoneStartSeq, 2, '0', STR_PAD_LEFT);
                            $previewEnd   = ($prevRow?->section?->section_code ?? '') . ($prevRow?->row_code ?? '') . str_pad((string) $zoneEndSeq, 2, '0', STR_PAD_LEFT);
                            $pairCount    = $zoneEndSeq - $zoneStartSeq + 1;
                        @endphp
                        <div class="rounded-lg bg-primary-50 dark:bg-primary-900/20 p-3 text-sm text-primary-700 dark:text-primary-300">
                            <span class="font-medium">{{ __('floor.zone_preview') }}:</span>
                            <span class="font-semibold">
                                {{ $previewStart === $previewEnd ? $previewStart : $previewStart . '–' . $previewEnd }}
                            </span>
                            <span class="text-xs opacity-75">({{ trans_choice('floor.pairs_count', $pairCount, ['count' => $pairCount]) }})</span>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('app.cover_count') }}</label>
                            <input id="cover-count" type="number" wire:model="coverCount" min="1" placeholder="—" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 px-3">
                            @error('coverCount') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('billing.delivery_label') }}</label>
                            <input id="delivery-label" type="text" wire:model="deliveryLabel" placeholder="{{ __('app.delivery_label_example') }}" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 px-3">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('app.notes') }}</label>
                        <textarea id="notes" wire:model="notes" rows="2" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm p-3"></textarea>
                        @error('notes') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="w-full flex justify-center items-center rounded-lg bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 min-h-[48px] transition-colors"
                        >
                            {{ __('floor.open_group') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
