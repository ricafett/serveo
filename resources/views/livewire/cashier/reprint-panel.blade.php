<div class="p-4 sm:p-6 lg:p-8">
    <div class="max-w-4xl mx-auto">
        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ __('Reprint & Documents') }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if($group)
                        {{ $group->display_code }}
                    @else
                        {{ __('Select a billing group to view printable documents.') }}
                    @endif
                </p>
            </div>
        </div>

        {{-- Messages --}}
        @if($errorMessage)
            <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 p-3 text-sm text-red-600 dark:text-red-400">
                {{ $errorMessage }}
            </div>
        @endif
        @if($successMessage)
            <div class="mb-4 rounded-lg bg-green-50 dark:bg-green-900/20 p-3 text-sm text-green-600 dark:text-green-400">
                {{ $successMessage }}
            </div>
        @endif

        @if($group)
            {{-- Bills --}}
            <div class="mb-6 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-white">{{ __('Bills') }}</h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $group->billingDocuments?->count() ?? 0 }}</span>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($group->billingDocuments ?? [] as $doc)
                        <div class="px-4 py-3 flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ $doc->document_number }}
                                    @if($doc->is_reprint)
                                        <span class="text-xs text-amber-600 dark:text-amber-400">({{ __('Reprint') }})</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $doc->requested_at?->format('H:i') }} · {{ number_format($doc->total_amount, 2) }} · {{ $doc->document_status }}</div>
                            </div>
                            @can('billing_document.reprint')
                                <button type="button" wire:click="reprintBill({{ $doc->id }})" class="rounded-lg px-3 py-2 text-sm font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20 min-h-[44px] transition-colors">
                                    {{ __('Reprint') }}
                                </button>
                            @endcan
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No bills found.') }}</div>
                    @endforelse
                </div>
            </div>

            {{-- Production Tickets --}}
            <div class="mb-6 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 overflow-hidden">
                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-900 dark:text-white">{{ __('Production Tickets') }}</h2>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $group->productionTickets?->count() ?? 0 }}</span>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($group->productionTickets ?? [] as $ticket)
                        <div class="px-4 py-3 flex items-center justify-between">
                            <div>
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    #{{ $ticket->id }} · {{ $ticket->ticket_type }}
                                    @if($ticket->is_reprint)
                                        <span class="text-xs text-amber-600 dark:text-amber-400">({{ __('Reprint') }})</span>
                                    @endif
                                    @if($ticket->is_void_slip)
                                        <span class="text-xs text-red-600 dark:text-red-400">({{ __('Void') }})</span>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $ticket->requested_at?->format('H:i') }} · {{ $ticket->ticket_status }} · {{ $ticket->items?->count() ?? 0 }} {{ __('items') }}</div>
                            </div>
                            @can('production_ticket.reprint')
                                @if(! $ticket->is_void_slip)
                                    <button type="button" wire:click="reprintTicket({{ $ticket->id }})" class="rounded-lg px-3 py-2 text-sm font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-900/20 min-h-[44px] transition-colors">
                                        {{ __('Reprint') }}
                                    </button>
                                @endif
                            @endcan
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No tickets found.') }}</div>
                    @endforelse
                </div>
            </div>
        @else
            <div class="rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 p-8 text-center">
                <p class="text-gray-500 dark:text-gray-400">{{ __('Open a billing group from lookup to access reprint options.') }}</p>
            </div>
        @endif
    </div>
</div>
