<?php

namespace App\Domain\Session;

use App\Models\CashMovement;
use App\Models\OrderItem;
use App\Models\PaymentRecord;
use App\Models\SalePayment;
use App\Models\ServiceSession;
use App\Models\User;
use Illuminate\Support\Collection;

class SessionTotalsService
{
    /**
     * Compute per-cashier totals for the given session.
     *
     * Returns a collection of arrays, one per user who has any
     * financial activity (cash movement, bill payment, or sale payment)
     * in the session.
     */
    public function computeCashierTotals(ServiceSession $session): Collection
    {
        $movements = $this->cashMovementsByUser($session);
        $billPayments = $this->billPaymentsByUser($session);
        $salePayments = $this->salePaymentsByUser($session);

        $allUserIds = collect([
            ...$movements->keys(),
            ...$billPayments->keys(),
            ...$salePayments->keys(),
        ])->unique();

        $users = User::whereIn('id', $allUserIds)->get()->keyBy('id');

        return $allUserIds->map(function (int $userId) use ($movements, $billPayments, $salePayments, $users) {
            $user = $users->get($userId);
            $in = (float) ($movements->get($userId)['in'] ?? 0);
            $out = (float) ($movements->get($userId)['out'] ?? 0);
            $bills = (float) ($billPayments->get($userId) ?? 0);
            $sales = (float) ($salePayments->get($userId) ?? 0);
            $net = round($in - $out + $bills + $sales, 2);

            return [
                'user_id' => $userId,
                'user_name' => $user?->name ?? "User #{$userId}",
                'cash_in' => $in,
                'cash_out' => $out,
                'bill_payments' => $bills,
                'sale_payments' => $sales,
                'net' => $net,
            ];
        })->sortByDesc('net')->values();
    }

    /**
     * Compute session-level summary totals (not per-cashier).
     */
    public function computeSummary(ServiceSession $session): array
    {
        $cashierTotals = $this->computeCashierTotals($session);

        return [
            'cash_in' => round($cashierTotals->sum('cash_in'), 2),
            'cash_out' => round($cashierTotals->sum('cash_out'), 2),
            'bill_payments' => round($cashierTotals->sum('bill_payments'), 2),
            'sale_payments' => round($cashierTotals->sum('sale_payments'), 2),
            'net_cash_movement' => round($cashierTotals->sum('cash_in') - $cashierTotals->sum('cash_out'), 2),
            'total_payments' => round($cashierTotals->sum('bill_payments') + $cashierTotals->sum('sale_payments'), 2),
            'overall_balance' => round($cashierTotals->sum('net'), 2),
        ];
    }

    /**
     * Compute inventory movements: all non-voided order items in the session,
     * grouped by menu item + variant, sorted by quantity descending.
     * Modifiers are ignored in the grouping as requested.
     */
    public function computeInventoryMovements(ServiceSession $session): Collection
    {
        $items = OrderItem::whereHas('header.billingGroup', function ($q) use ($session) {
            $q->where('service_session_id', $session->id);
        })
            ->whereNull('voided_at')
            ->with('menuItem')
            ->get();

        return $items
            ->groupBy(function (OrderItem $item): string {
                $key = (string) $item->menu_item_id;
                if ($item->variant_name) {
                    $key .= '|' . $item->variant_name;
                }
                return $key;
            })
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'menu_item_id' => $first->menu_item_id,
                    'menu_item_name' => $first->menuItem?->display_name ?? __('ticket.unknown_item', ['id' => $first->menu_item_id]),
                    'variant_name' => $first->variant_name,
                    'total_qty' => $group->sum('quantity'),
                ];
            })
            ->sortByDesc('total_qty')
            ->values();
    }

    private function cashMovementsByUser(ServiceSession $session): Collection
    {
        return CashMovement::where('service_session_id', $session->id)
            ->get()
            ->groupBy('cashier_user_id')
            ->map(function (Collection $group): array {
                $in = $group->where('movement_type', CashMovement::TYPE_CASH_IN)->sum('amount');
                $out = $group->where('movement_type', CashMovement::TYPE_CASH_OUT)->sum('amount');

                return ['in' => (float) $in, 'out' => (float) $out];
            });
    }

    private function billPaymentsByUser(ServiceSession $session): Collection
    {
        return PaymentRecord::whereHas('billingGroup', function ($q) use ($session) {
            $q->where('service_session_id', $session->id);
        })
            ->where('is_voided', false)
            ->get()
            ->groupBy('recorded_by_user_id')
            ->map(fn (Collection $group): float => (float) $group->sum('amount'));
    }

    private function salePaymentsByUser(ServiceSession $session): Collection
    {
        return SalePayment::whereHas('sale', function ($q) use ($session) {
            $q->where('service_session_id', $session->id);
        })
            ->where('is_voided', false)
            ->get()
            ->groupBy('recorded_by_user_id')
            ->map(fn (Collection $group): float => (float) $group->sum('amount'));
    }
}
