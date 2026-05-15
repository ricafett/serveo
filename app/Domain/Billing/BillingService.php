<?php

namespace App\Domain\Billing;

use App\Domain\Audit\Audit;
use App\Domain\ChecksPermissions;
use App\Domain\Printing\PrintQueueService;
use App\Models\BillingDocument;
use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\CashierPrinterAssignment;
use App\Models\PaymentRecord;
use App\Models\Printer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingService
{
    use ChecksPermissions;

    public function __construct(private readonly PrintQueueService $printQueue) {}

    public function generateInternalBill(BillingGroup $group, User $cashier): BillingDocument
    {
        $this->ensureCan($cashier, 'billing_document.create');
        if ($group->is_closed) {
            throw new RuntimeException('Cannot bill a closed group.');
        }

        return DB::transaction(function () use ($group, $cashier) {
            $printer = $this->resolveCashierPrinter($cashier);
            if (! $printer) {
                throw new RuntimeException('No cashier printer is assigned to this user.');
            }

            $subtotal = $group->chargesTotal();
            $bill = BillingDocument::create([
                'billing_group_id'     => $group->id,
                'printer_id'           => $printer->id,
                'document_type'        => BillingDocument::TYPE_INTERNAL_BILL,
                'document_status'      => 'GENERATED',
                'document_number'      => 'B-'.date('Ymd').'-'.str_pad((string) (BillingDocument::whereDate('created_at', today())->count() + 1), 4, '0', STR_PAD_LEFT),
                'subtotal_amount'      => $subtotal,
                'total_amount'         => $subtotal,
                'requested_at'         => now(),
                'is_reprint'           => false,
                'created_by_user_id'   => $cashier->id,
            ]);

            $this->printQueue->enqueueBill($bill, $cashier);

            // Set status to CHECK_REQUESTED if currently ACTIVE and that status exists.
            if ($group->status?->code === BillingStatus::ACTIVE) {
                $check = BillingStatus::where('code', BillingStatus::CHECK_REQUESTED)->value('id');
                if ($check) {
                    $group->update(['billing_status_id' => $check]);
                }
            }

            Audit::record(
                'BILL_GENERATED',
                "Conta {$bill->document_number} gerada para {$group->display_code}",
                ['total' => (float) $bill->total_amount],
                [
                    'billing_group_id'    => $group->id,
                    'service_session_id'  => $group->service_session_id,
                    'billing_document_id' => $bill->id,
                    'actor_user_id'       => $cashier->id,
                ],
            );

            return $bill;
        });
    }

    public function reprintBill(BillingDocument $original, User $cashier): BillingDocument
    {
        $this->ensureCan($cashier, 'billing_document.reprint');

        $printer = $this->resolveCashierPrinter($cashier) ?? $original->printer;
        if (! $printer) {
            throw new RuntimeException('No cashier printer available for reprint.');
        }

        $reprint = BillingDocument::create([
            'billing_group_id'                 => $original->billing_group_id,
            'printer_id'                       => $printer->id,
            'document_type'                    => BillingDocument::TYPE_BILL_REPRINT,
            'document_status'                  => 'GENERATED',
            'document_number'                  => $original->document_number.'-R'.($original->billingGroup?->billingDocuments()?->where('is_reprint', true)?->count() + 1),
            'subtotal_amount'                  => $original->subtotal_amount,
            'total_amount'                     => $original->total_amount,
            'requested_at'                     => now(),
            'reprint_of_billing_document_id'   => $original->id,
            'is_reprint'                       => true,
            'created_by_user_id'               => $cashier->id,
        ]);

        $this->printQueue->enqueueBill($reprint, $cashier);

        Audit::record(
            'BILL_REPRINTED',
            "Reimpressão de conta #{$original->id}",
            [],
            [
                'billing_group_id'    => $original->billing_group_id,
                'service_session_id'  => $original->billingGroup?->service_session_id,
                'billing_document_id' => $reprint->id,
                'actor_user_id'       => $cashier->id,
            ],
        );

        return $reprint;
    }

    public function recordPayment(BillingGroup $group, User $cashier, float $amount, string $label, ?string $notes = null): PaymentRecord
    {
        $this->ensureCan($cashier, 'payment.record');

        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        return DB::transaction(function () use ($group, $cashier, $amount, $label, $notes) {
            $payment = PaymentRecord::create([
                'billing_group_id'     => $group->id,
                'recorded_by_user_id'  => $cashier->id,
                'recorded_at'          => now(),
                'amount'               => $amount,
                'payment_label'        => $label,
                'notes'                => $notes,
                'is_voided'            => false,
            ]);

            // Auto-update status based on resulting balance.
            $balance = $group->balance();
            if ($balance <= 0.0001) {
                $closed = BillingStatus::where('code', BillingStatus::CLOSED)->value('id');
                $group->update([
                    'billing_status_id' => $closed,
                    'is_closed'         => true,
                    'closed_at'         => now(),
                ]);
                $group->openOccupiedZones()->update(['is_open' => false, 'released_at' => now()]);
            } else {
                $partial = BillingStatus::where('code', BillingStatus::PARTIALLY_PAID)->value('id');
                if ($partial) {
                    $group->update(['billing_status_id' => $partial]);
                }
            }

            Audit::record(
                'PAYMENT_RECORDED',
                "Pagamento {$label} de {$amount} EUR registado para {$group->display_code}",
                ['amount' => $amount, 'label' => $label],
                [
                    'billing_group_id'   => $group->id,
                    'service_session_id' => $group->service_session_id,
                    'payment_record_id'  => $payment->id,
                    'actor_user_id'      => $cashier->id,
                ],
            );

            return $payment;
        });
    }

    public function voidPayment(PaymentRecord $payment, User $cashier, ?string $notes = null): void
    {
        $this->ensureCan($cashier, 'payment.void');

        if ($payment->is_voided) {
            return;
        }
        $payment->update([
            'is_voided'         => true,
            'voided_at'         => now(),
            'voided_by_user_id' => $cashier->id,
            'notes'             => trim(($payment->notes ?? '')."\nVOID: ".$notes),
        ]);

        Audit::record(
            'PAYMENT_VOIDED',
            "Pagamento #{$payment->id} anulado",
            ['notes' => $notes],
            [
                'billing_group_id'   => $payment->billing_group_id,
                'service_session_id' => $payment->billingGroup?->service_session_id,
                'payment_record_id'  => $payment->id,
                'actor_user_id'      => $cashier->id,
            ],
        );
    }

    private function resolveCashierPrinter(User $cashier): ?Printer
    {
        $assignment = CashierPrinterAssignment::with('printer')
            ->where('user_id', $cashier->id)
            ->where('is_active', true)
            ->first();
        if ($assignment?->printer) {
            return $assignment->printer;
        }
        // fallback: any active BILL printer
        return Printer::where('printer_type', Printer::TYPE_BILL)->where('is_active', true)->first();
    }
}
