<?php

namespace App\Livewire\Cashier;

use App\Domain\Billing\BillingService;
use App\Domain\Printing\PrintQueueService;
use App\Models\BillingDocument;
use App\Models\BillingGroup;
use App\Models\ProductionTicket;
use App\Models\ServiceSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ReprintPanel extends Component
{
    public ?int $billingGroupId = null;

    public ?BillingGroup $group = null;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function mount(?int $billingGroupId = null): void
    {
        $this->billingGroupId = $billingGroupId;
        if ($this->billingGroupId) {
            $this->loadGroup();
        }
    }

    public function loadGroup(): void
    {
        $this->group = BillingGroup::with([
            'billingDocuments' => fn ($q) => $q->orderBy('created_at', 'desc'),
            'productionTickets' => fn ($q) => $q->orderBy('requested_at', 'desc'),
        ])->find($this->billingGroupId);
    }

    public function reprintBill(int $billId): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        if (! Auth::user()?->can('billing_document.reprint')) {
            $this->errorMessage = __('Unauthorized to reprint bills.');

            return;
        }

        if (! ServiceSession::where('status', 'OPEN')->exists()) {
            $this->errorMessage = __('No open service session.');

            return;
        }

        try {
            $original = BillingDocument::findOrFail($billId);
            app(BillingService::class)->reprintBill($original, Auth::user());
            $this->successMessage = __('Bill reprint sent to printer.');
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function reprintTicket(int $ticketId): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        if (! Auth::user()?->can('production_ticket.reprint')) {
            $this->errorMessage = __('Unauthorized to reprint tickets.');

            return;
        }

        if (! ServiceSession::where('status', 'OPEN')->exists()) {
            $this->errorMessage = __('No open service session.');

            return;
        }

        try {
            $original = ProductionTicket::with('printer')->findOrFail($ticketId);

            $reprint = ProductionTicket::create([
                'service_session_id' => $original->service_session_id,
                'billing_group_id' => $original->billing_group_id,
                'occupied_zone_id' => $original->occupied_zone_id,
                'printer_id' => $original->printer_id,
                'ticket_type' => $original->ticket_type,
                'ticket_status' => 'PENDING',
                'delivery_reference_label' => $original->delivery_reference_label,
                'requested_at' => now(),
                'is_void_slip' => false,
                'is_reprint' => true,
                'reprint_of_ticket_id' => $original->id,
                'created_by_user_id' => Auth::user()?->id,
            ]);
            $reprint->items()->sync($original->items->pluck('id'));

            app(PrintQueueService::class)->enqueueProductionTicket($reprint, Auth::user());

            $this->successMessage = __('Ticket reprint sent to printer.');
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.cashier.reprint-panel')
            ->layout('layouts.operational');
    }
}
