<?php

namespace App\Domain\CashDrawer;

use App\Domain\Audit\Audit;
use App\Models\CashMovement;
use App\Models\PaymentRecord;
use App\Models\SalePayment;
use App\Models\ServiceSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CashDrawerService
{
    /**
     * Compute the cash drawer balance for a given cashier and session.
     *
     * Balance = SUM(CASH_IN) + SUM(non-voided billing group payments by this cashier)
     *         + SUM(non-voided sale payments by this cashier) - SUM(CASH_OUT)
     */
    public function getBalance(User $cashier, ServiceSession $session): float
    {
        $cashIn = (float) CashMovement::where('cashier_user_id', $cashier->id)
            ->where('service_session_id', $session->id)
            ->where('movement_type', CashMovement::TYPE_CASH_IN)
            ->sum('amount');

        $cashOut = (float) CashMovement::where('cashier_user_id', $cashier->id)
            ->where('service_session_id', $session->id)
            ->where('movement_type', CashMovement::TYPE_CASH_OUT)
            ->sum('amount');

        $billingPayments = (float) PaymentRecord::where('recorded_by_user_id', $cashier->id)
            ->where('is_voided', false)
            ->whereHas('billingGroup', fn ($q) => $q->where('service_session_id', $session->id))
            ->sum('amount');

        $salePayments = (float) SalePayment::where('recorded_by_user_id', $cashier->id)
            ->where('is_voided', false)
            ->whereHas('sale', fn ($q) => $q->where('service_session_id', $session->id))
            ->sum('amount');

        return round($cashIn + $billingPayments + $salePayments - $cashOut, 2);
    }

    /**
     * Get all cash movements for a cashier and session, ordered by recorded_at desc.
     */
    public function getMovements(User $cashier, ServiceSession $session): \Illuminate\Support\Collection
    {
        return CashMovement::where('cashier_user_id', $cashier->id)
            ->where('service_session_id', $session->id)
            ->orderBy('recorded_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Get payment inflows (billing group payments + sale payments) for display
     * alongside cash movements in the drawer history.
     */
    public function getPaymentInflows(User $cashier, ServiceSession $session): \Illuminate\Support\Collection
    {
        $billingPayments = PaymentRecord::with('billingGroup')
            ->where('recorded_by_user_id', $cashier->id)
            ->where('is_voided', false)
            ->whereHas('billingGroup', fn ($q) => $q->where('service_session_id', $session->id))
            ->get()
            ->map(fn (PaymentRecord $p) => [
                'type' => 'payment_billing',
                'recorded_at' => $p->recorded_at,
                'amount' => (float) $p->amount,
                'label' => $p->payment_label,
                'source' => $p->billingGroup?->display_code ?? '—',
                'source_id' => $p->billing_group_id,
            ]);

        $salePayments = SalePayment::with('sale')
            ->where('recorded_by_user_id', $cashier->id)
            ->where('is_voided', false)
            ->whereHas('sale', fn ($q) => $q->where('service_session_id', $session->id))
            ->get()
            ->map(fn (SalePayment $p) => [
                'type' => 'payment_sale',
                'recorded_at' => $p->recorded_at,
                'amount' => (float) $p->amount,
                'label' => $p->payment_label,
                'source' => $p->sale?->display_code ?? '—',
                'source_id' => $p->sale_id,
            ]);

        return $billingPayments->concat($salePayments)
            ->sortByDesc('recorded_at')
            ->values();
    }

    /**
     * Record a cash movement (CASH_IN or CASH_OUT) for a cashier in a session.
     */
    public function recordMovement(User $cashier, ServiceSession $session, string $type, float $amount, string $label, ?string $notes = null): CashMovement
    {
        if (! $session->isOpen()) {
            throw new RuntimeException(__('cashdrawer.no_session'));
        }

        if ($amount <= 0) {
            throw new RuntimeException(__('cashdrawer.amount_positive'));
        }

        if (! in_array($type, [CashMovement::TYPE_CASH_IN, CashMovement::TYPE_CASH_OUT], true)) {
            throw new RuntimeException(__('cashdrawer.invalid_type'));
        }

        // For CASH_OUT, reject if balance would go negative
        if ($type === CashMovement::TYPE_CASH_OUT) {
            $currentBalance = $this->getBalance($cashier, $session);
            if ($amount > $currentBalance + 0.0001) {
                throw new RuntimeException(__('cashdrawer.insufficient_balance'));
            }
        }

        return DB::transaction(function () use ($cashier, $session, $type, $amount, $label, $notes) {
            $movement = CashMovement::create([
                'service_session_id' => $session->id,
                'cashier_user_id' => $cashier->id,
                'movement_type' => $type,
                'amount' => $amount,
                'label' => $label,
                'notes' => $notes,
                'recorded_at' => now(),
            ]);

            $direction = $type === CashMovement::TYPE_CASH_IN ? 'IN' : 'OUT';

            Audit::record(
                'CASH_MOVEMENT_RECORDED',
                "Cash {$direction} de {$amount} € registado por {$cashier->name} na sessão {$session->session_label}",
                ['type' => $type, 'amount' => $amount, 'label' => $label],
                [
                    'service_session_id' => $session->id,
                    'actor_user_id' => $cashier->id,
                    'entity_type' => CashMovement::class,
                    'entity_id' => $movement->id,
                ],
            );

            return $movement;
        });
    }

    /**
     * Build a combined timeline of cash movements and payment inflows,
     * sorted by recorded_at desc.
     */
    public function getTimeline(User $cashier, ServiceSession $session): array
    {
        $movements = $this->getMovements($cashier, $session)->map(fn (CashMovement $m) => [
            'type' => $m->movement_type,
            'recorded_at' => $m->recorded_at,
            'amount' => (float) $m->amount,
            'label' => $m->label,
            'notes' => $m->notes,
            'source' => null,
            'source_id' => null,
        ]);

        $inflows = $this->getPaymentInflows($cashier, $session);

        return $movements->concat($inflows->map(fn ($item) => [
            'type' => $item['type'],
            'recorded_at' => $item['recorded_at'],
            'amount' => $item['amount'],
            'label' => $item['label'],
            'notes' => null,
            'source' => $item['source'],
            'source_id' => $item['source_id'],
        ]))
            ->sortByDesc('recorded_at')
            ->values()
            ->all();
    }
}
