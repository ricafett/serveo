<div class="p-4 sm:p-6 lg:p-8" x-data="{ createModalOpen: @entangle('showCreateModal') }">
    <div class="max-w-7xl mx-auto">
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('floor.title') }}</h1>
            @if($this->session)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->session->session_label }}
                </p>
            @else
                <p class="mt-1 text-sm text-red-500 dark:text-red-400">{{ __('floor.no_session') }}</p>
            @endif
        </div>

        {{-- Filter Toggle (applies to entire page: map + groups list) --}}
        <div class="mb-4 flex gap-2">
            <button wire:click="$set('filter', 'all')"
                class="flex-1 flex items-center justify-center gap-2 min-h-[48px] px-4 py-2.5 text-base rounded-xl font-semibold transition-all
                    {{ $filter === 'all'
                        ? 'bg-indigo-600 text-white shadow-md ring-2 ring-indigo-300 dark:ring-indigo-800'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600' }}">
                {{ __('floor.filter_all') }}
            </button>
            <button wire:click="$set('filter', 'favorites')"
                class="flex-1 flex items-center justify-center gap-2 min-h-[48px] px-4 py-2.5 text-base rounded-xl font-semibold transition-all
                    {{ $filter === 'favorites'
                        ? 'bg-indigo-600 text-white shadow-md ring-2 ring-indigo-300 dark:ring-indigo-800'
                        : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600' }}">
                <span class="text-lg">★</span> {{ __('floor.filter_favorites') }}
            </button>
        </div>

        {{-- Sections & Rows --}}
        <div class="space-y-6">
            @forelse($this->sections as $section)
                <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                    {{-- Section Header --}}
                    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800">
                        <h2 class="font-semibold text-gray-900 dark:text-white">{{ $section->name }}</h2>
                    </div>

                    {{-- Rows --}}
                    <div class="p-4 space-y-4">
                        @foreach($section->rows as $row)
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

                                {{-- Seat Pair Ranges --}}
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($this->getRowRanges($row) as $range)
                                        {{-- Free ranges always visible --}}
                                        @if($range['type'] === 'free')
                                            <button
                                                type="button"
                                                wire:click="selectRange({{ $row->id }}, {{ $range['start'] }}, {{ $range['end'] }})"
                                                class="rounded-lg px-3 py-2 text-sm font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:hover:bg-emerald-900/30 transition-colors min-h-[44px] flex items-center"
                                                title="{{ __('floor.tap_to_open') }}"
                                            >
                                                {{ $range['start'] }}–{{ $range['end'] }}
                                            </button>
                                        {{-- Occupied ranges: hidden if filter=favorites and group not favorited --}}
                                        @elseif($filter !== 'favorites' || ($range['group'] && in_array($range['group']->id, $this->favoriteGroupIds)))
                                            <button
                                                type="button"
                                                wire:click="openExistingGroup({{ $range['group']->id ?? 0 }})"
                                                class="rounded-lg px-3 py-2 text-sm font-medium bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30 transition-colors min-h-[44px] flex items-center gap-1.5"
                                                title="{{ $range['group']->display_code ?? '' }} — {{ $range['group']->status?->display_name ?? $range['group']->status?->code ?? '' }}"
                                            >
                                                <span class="w-2 h-2 rounded-full bg-amber-500 dark:bg-amber-400"></span>
                                                {{ $range['start'] }}–{{ $range['end'] }}
                                                <span class="text-xs opacity-75">{{ $range['group']->display_code ?? '' }}</span>
                                                @if($range['server'] ?? null)
                                                    <span class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded px-1.5 py-0.5 font-medium">
                                                        {{ strtoupper(substr($range['server']->name, 0, 2)) }}
                                                    </span>
                                                @endif
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
                        <div class="relative rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                            {{-- Favorite Button (outside link so it doesn't trigger navigation) --}}
                            @php
                                $favPivot = $group->favoritedBy->where('id', Auth::id())->first();
                                $isFavorited = (bool) $favPivot;
                                $isAutoFavorite = $isFavorited && $favPivot->pivot->is_manual === false;
                            @endphp
                            <button
                                type="button"
                                wire:click="toggleFavorite({{ $group->id }})"
                                class="absolute top-3 right-3 z-10 flex items-center justify-center min-h-[44px] min-w-[44px] rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                                title="{{ $isAutoFavorite ? __('floor.auto_favorite') : ($isFavorited ? __('floor.unfavorite') : __('floor.favorite')) }}"
                            >
                                @if($isFavorited)
                                    <span class="text-2xl text-yellow-500">★</span>
                                @else
                                    <span class="text-2xl text-gray-300 dark:text-gray-600">☆</span>
                                @endif
                            </button>

                            <a
                                href="{{ route('billing-groups.detail', ['id' => $group->id]) }}"
                                wire:navigate
                                class="block p-4 pr-14 hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-gray-900 dark:text-white">{{ $group->display_code }}</span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                        {{ $group->status?->code === 'ACTIVE' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : '' }}
                                        {{ $group->status?->code === 'WAITING' ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400' : '' }}
                                        {{ $group->status?->code === 'CHECK_REQUESTED' ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' : '' }}
                                    ">
                                        {{ $group->status?->display_name ?? $group->status?->code }}
                                    </span>
                                </div>
                                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    @foreach($group->occupiedZones as $zone)
                                        <div>
                                            {{ $zone->row?->section?->section_code }} · {{ $zone->row?->row_code }} · {{ $zone->rangeLabel() }}
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

                {{-- Selected Range Info --}}
                @if($selectedRowId && $zoneStartSeq && $zoneEndSeq)
                    @php
                        $selectedRow = \App\Models\Row::with('section')->find($selectedRowId);
                    @endphp
                    <div class="mb-4 rounded-lg bg-primary-50 dark:bg-primary-900/20 p-3 text-sm text-primary-700 dark:text-primary-300">
                        <div class="font-medium">{{ __('floor.selected_range') }}</div>
                        <div>{{ $selectedRow?->section?->name }} · {{ __('floor.row') }} {{ $selectedRow?->row_code }} · {{ __('app.pairs') }} {{ $zoneStartSeq }}–{{ $zoneEndSeq }}</div>
                    </div>
                @endif

                {{-- Form --}}
                <form wire:submit.prevent="createBillingGroup" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('app.status') }}</label>
                        <select id="status-code" wire:model="statusCode" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 px-3">
                            @foreach(\App\Models\BillingStatus::where('is_active', true)->orderBy('sort_order')->get() as $status)
                                <option value="{{ $status->code }}">{{ $status->display_name ?? $status->code }}</option>
                            @endforeach
                        </select>
                        @error('statusCode') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

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

                    {{-- Hidden zone fields for validation --}}
                    <input type="hidden" wire:model="zoneRowId">
                    <input type="hidden" wire:model="zoneStartSeq">
                    <input type="hidden" wire:model="zoneEndSeq">

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
