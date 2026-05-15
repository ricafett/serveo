<div class="p-4 sm:p-6 lg:p-8" x-data="{ createModalOpen: @entangle('showCreateModal') }">
    <div class="max-w-7xl mx-auto">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Floor') }}</h1>
                @if($this->session)
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $this->session->session_label }} · {{ $this->openGroups->count() }} {{ __('open groups') }}
                    </p>
                @else
                    <p class="mt-1 text-sm text-red-500 dark:text-red-400">{{ __('No open service session.') }}</p>
                @endif
            </div>
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
                                        {{ __('Row') }} {{ $row->row_code }}
                                    </span>
                                    <span class="text-xs text-gray-400 dark:text-gray-500">
                                        {{ $row->seatPairs->count() }} {{ __('pairs') }}
                                    </span>
                                </div>

                                {{-- Seat Pair Ranges --}}
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($this->getRowRanges($row) as $range)
                                        @if($range['type'] === 'free')
                                            <button
                                                type="button"
                                                wire:click="selectRange({{ $row->id }}, {{ $range['start'] }}, {{ $range['end'] }})"
                                                class="rounded-lg px-3 py-2 text-sm font-medium bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:text-emerald-400 dark:hover:bg-emerald-900/30 transition-colors min-h-[44px] flex items-center"
                                                title="{{ __('Tap to open billing group') }}"
                                            >
                                                {{ $range['start'] }}–{{ $range['end'] }}
                                            </button>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="openExistingGroup({{ $range['group']->id }})"
                                                class="rounded-lg px-3 py-2 text-sm font-medium bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30 transition-colors min-h-[44px] flex items-center gap-1.5"
                                                title="{{ $range['group']->display_code }} — {{ $range['group']->status?->display_name ?? $range['group']->status?->code }}"
                                            >
                                                <span class="w-2 h-2 rounded-full bg-amber-500 dark:bg-amber-400"></span>
                                                {{ $range['start'] }}–{{ $range['end'] }}
                                                <span class="text-xs opacity-75">{{ $range['group']->display_code }}</span>
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
                    <p class="text-gray-500 dark:text-gray-400">{{ __('No sections configured.') }}</p>
                </div>
            @endforelse
        </div>

        {{-- Open Groups Quick List --}}
        @if($this->openGroups->count() > 0)
            <div class="mt-8">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">{{ __('Open Billing Groups') }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($this->openGroups as $group)
                        <a
                            href="{{ route('billing-groups.detail', ['id' => $group->id]) }}"
                            wire:navigate
                            class="block rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-4 hover:border-primary-300 dark:hover:border-primary-700 transition-colors"
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
                                    <div>{{ $zone->row?->section?->section_code }} · {{ $zone->row?->row_code }} · {{ $zone->rangeLabel() }}</div>
                                @endforeach
                            </div>
                            @if($group->cover_count)
                                <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $group->cover_count }} {{ __('covers') }}</div>
                            @endif
                        </a>
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
                    <h2 class="text-lg font-bold text-gray-900 dark:text-white">{{ __('Open Billing Group') }}</h2>
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
                        <div class="font-medium">{{ __('Selected range') }}</div>
                        <div>{{ $selectedRow?->section?->name }} · {{ __('Row') }} {{ $selectedRow?->row_code }} · {{ __('Pairs') }} {{ $zoneStartSeq }}–{{ $zoneEndSeq }}</div>
                    </div>
                @endif

                {{-- Form --}}
                <form wire:submit.prevent="createBillingGroup" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Status') }}</label>
                        <select id="status-code" wire:model="statusCode" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 px-3">
                            @foreach(\App\Models\BillingStatus::where('is_active', true)->orderBy('sort_order')->get() as $status)
                                <option value="{{ $status->code }}">{{ $status->display_name ?? $status->code }}</option>
                            @endforeach
                        </select>
                        @error('statusCode') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Cover Count') }}</label>
                            <input id="cover-count" type="number" wire:model="coverCount" min="1" placeholder="—" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 px-3">
                            @error('coverCount') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Delivery Label') }}</label>
                            <input id="delivery-label" type="text" wire:model="deliveryLabel" placeholder="{{ __('e.g. Center') }}" class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm h-11 px-3">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('Notes') }}</label>
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
                            {{ __('Open Billing Group') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
