<?php

namespace App\Livewire\Cashier;

use App\Domain\CashDrawer\CashDrawerService;
use App\Domain\Printing\PrintQueueService;
use App\Jobs\OpenCashDrawerJob;
use App\Models\CashierPrinterAssignment;
use App\Models\CashMovement;
use App\Models\ServiceSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CashDrawerIndex extends Component
{
    public ?ServiceSession $session = null;
    public float $balance = 0.00;
    public array $timeline = [];
    public bool $showForm = false;
    public string $movementType = 'CASH_IN';
    public ?float $movementAmount = null;
    public string $movementLabel = '';
    public ?string $movementNotes = null;
    public ?string $errorMessage = null;
    public ?string $successMessage = null;
    public bool $isSubmitting = false;

    public bool $isOpeningDrawer = false;

    public bool $isPrintingTotals = false;

    public function mount(): void
    {
        $this->session = ServiceSession::where('status', 'OPEN')->latest('starts_at')->first();

        if ($this->session) {
            $this->refreshData();
        }
    }

    public function recordMovement(): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $this->errorMessage = null;
        $this->successMessage = null;

        if (! $this->session?->isOpen()) {
            $this->errorMessage = __('cashdrawer.no_session');

            return;
        }

        if ($this->movementAmount === null || $this->movementAmount <= 0) {
            $this->errorMessage = __('cashdrawer.amount_positive');

            return;
        }

        try {
            $this->isSubmitting = true;

            /** @var CashDrawerService $service */
            $service = app(CashDrawerService::class);
            $service->recordMovement(
                Auth::user(),
                $this->session,
                $this->movementType,
                $this->movementAmount,
                $this->movementLabel,
                $this->movementNotes,
            );

            $this->successMessage = __('cashdrawer.movement_recorded');
            $this->showForm = false;
            $this->movementAmount = null;
            $this->movementLabel = '';
            $this->movementNotes = null;
            $this->movementType = 'CASH_IN';

            $this->refreshData();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function refreshData(): void
    {
        if (! $this->session?->isOpen()) {
            return;
        }

        /** @var CashDrawerService $service */
        $service = app(CashDrawerService::class);
        $this->balance = $service->getBalance(Auth::user(), $this->session);
        $this->timeline = $service->getTimeline(Auth::user(), $this->session);
    }

    public function openDrawer(): void
    {
        if ($this->isOpeningDrawer) {
            return;
        }

        $this->errorMessage = null;
        $this->successMessage = null;

        $user = Auth::user();
        $assignment = CashierPrinterAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $assignment) {
            $this->errorMessage = __('cashdrawer.no_printer');

            return;
        }

        try {
            $this->isOpeningDrawer = true;
            OpenCashDrawerJob::dispatch($assignment->printer_id, $user->id)
                ->onQueue('prints');
            $this->successMessage = __('cashdrawer.drawer_opening');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isOpeningDrawer = false;
        }
    }

    public function printTotals(): void
    {
        if ($this->isPrintingTotals) {
            return;
        }

        $this->errorMessage = null;
        $this->successMessage = null;

        if (! $this->session?->isOpen()) {
            $this->errorMessage = __('cashdrawer.no_session');

            return;
        }

        $user = Auth::user();
        $assignment = CashierPrinterAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $assignment) {
            $this->errorMessage = __('cashdrawer.no_printer');

            return;
        }

        try {
            $this->isPrintingTotals = true;

            /** @var CashDrawerService $cashDrawer */
            $cashDrawer = app(CashDrawerService::class);
            $totals = $cashDrawer->getTotals($user, $this->session);

            // Add context for ticket rendering
            $totals['cashier_name'] = $user->name;
            $totals['session_label'] = $this->session->session_label;

            /** @var PrintQueueService $printQueue */
            $printQueue = app(PrintQueueService::class);
            $printQueue->enqueueCashierTotals($assignment->printer_id, $totals, $user);

            $this->successMessage = __('cashdrawer.totals_queued');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isPrintingTotals = false;
        }
    }

    public function render()
    {
        return view('livewire.cashier.cash-drawer-index')
            ->layout('layouts.operational');
    }
}
