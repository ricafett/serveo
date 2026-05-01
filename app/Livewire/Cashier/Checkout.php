<?php

namespace App\Livewire\Cashier;

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Models\BillingDocument;
use App\Models\BillingGroup;
use App\Models\PaymentRecord;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Checkout extends Component
{
    public int $id;
    public ?BillingGroup $group = null;

    // Payment form
    public ?float $paymentAmount = null;
    public ?string $paymentLabel = 'Cash';
    public ?string $paymentNotes = null;

    public ?string $errorMessage = null;
    public ?string $successMessage = null;

    public function mount(int $id): void
    {
        $this->id = $id;
        $this->loadGroup();
    }

    public function loadGroup(): void
    {
        $this->group = BillingGroup::with([
            'status',
            'occupiedZones.row.section',
            'orderHeaders' => fn ($q) => $q->orderBy('ordered_at', 'desc')->with(['items.menuItem', 'occupiedZone.row.section', 'orderedBy']),
            'paymentRecords' => fn ($q) => $q->orderBy('recorded_at', 'desc'),
            'billingDocuments' => fn ($q) => $q->orderBy('created_at', 'desc'),
        ])->findOrFail($this->id);
    }

    public function getChargesTotalProperty(): float
    {
        return $this->group?->chargesTotal() ?? 0;
    }

    public function getPaymentsTotalProperty(): float
    {
        return $this->group?->paymentsTotal() ?? 0;
    }

    public function getBalanceProperty(): float
    {
        return $this->group?->balance() ?? 0;
    }

    public function printBill(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        if (! Auth::user()?->can('billing_document.create')) {
            $this->errorMessage = __('Unauthorized to print bills.');
            return;
        }

        try {
            app(BillingService::class)->generateInternalBill($this->group, Auth::user());
            $this->successMessage = __('Bill sent to printer.');
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function recordPayment(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        if (! Auth::user()?->can('payment.record')) {
            $this->errorMessage = __('Unauthorized to record payments.');
            return;
        }

        $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01',
            'paymentLabel' => 'required|string|max:50',
        ]);

        try {
            app(BillingService::class)->recordPayment(
                $this->group,
                Auth::user(),
                (float) $this->paymentAmount,
                $this->paymentLabel,
                $this->paymentNotes,
            );
            $this->successMessage = __('Payment recorded.');
            $this->paymentAmount = null;
            $this->paymentNotes = null;
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function reopenGroup(): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        if (! Auth::user()?->can('billing_group.reopen')) {
            $this->errorMessage = __('Unauthorized to reopen groups.');
            return;
        }

        try {
            app(BillingGroupService::class)->reopen($this->group, Auth::user());
            $this->successMessage = __('Group reopened.');
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function reprintBill(int $billId): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        if (! Auth::user()?->can('billing_document.reprint')) {
            $this->errorMessage = __('Unauthorized to reprint bills.');
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

    public function goBack(): void
    {
        $this->redirect(route('lookup'), navigate: true);
    }

    public function render()
    {
        return view('livewire.cashier.checkout')
            ->layout('layouts.operational');
    }
}
