<?php

namespace App\Jobs;

use App\Domain\Audit\Audit;
use App\Domain\Printing\PrinterAdapterRegistry;
use App\Domain\Printing\TicketRenderer;
use App\Jobs\OpenCashDrawerJob;
use App\Models\BillingDocument;
use App\Models\CashierPrinterAssignment;
use App\Models\DocumentPrintConfig;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\PrinterRoute;
use App\Models\ProductionTicket;
use App\Models\SaleDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends one queued PrintJob to its target printer.
 *
 * Concurrency model (two locks, two purposes):
 *
 *   1. Atomic claim (DB):  UPDATE print_jobs SET status='IN_PROGRESS'
 *      WHERE id=? AND status='PENDING'. Only ONE worker can claim a
 *      given PrintJob. Prevents double-processing of the same job.
 *
 *   2. Per-printer lock (Redis, non-blocking): Cache::lock("printer:{id}").
 *      Only ONE worker can write to a given physical printer at a time.
 *      On contention → revert PrintJob to PENDING → re-dispatch with
 *      backoff → worker picks up next job immediately (no blocking).
 *
 * Attempt counting:
 *   - Lock contention does NOT count as a transport attempt. The attempt
 *     counter is decremented on contention revert.
 *   - max_attempts (default 4) counts genuine transport failures only.
 *
 * Backoff systems:
 *   - Contention backoff (jittered):  1s → 2s → 4s → 8s → 10s (capped)
 *   - Transport backoff (jittered):   3s → 6s → 10s (capped)
 *
 * Recovery:
 *   - serveo:recover-stuck-print-jobs (scheduled every 2min) reverts
 *     IN_PROGRESS jobs whose lock has expired.
 *   - The failed() method handles unhandled exceptions (worker killed).
 */
class DispatchPrintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Laravel won't retry — we manage retries via self-dispatch. */
    public int $tries = 1;

    public function __construct(
        public int $printJobId,
        public int $lockContentionAttempt = 0,
    ) {}

    /**
     * @throws Throwable Only for truly unexpected errors that failed() should catch.
     */
    public function handle(PrinterAdapterRegistry $registry): void
    {
        // ── 1. Atomic claim: only if claimable AND under max_attempts ──
        //     Accepts PENDING (fresh) or FAILED (retry after transport failure).
        //     Rejects PRINTED, CANCELED, or attempts >= max_attempts.
        $claimed = PrintJob::where('id', $this->printJobId)
            ->whereIn('status', [PrintJob::STATUS_PENDING, PrintJob::STATUS_FAILED])
            ->where('attempts', '<', DB::raw('max_attempts'))
            ->update([
                'status' => PrintJob::STATUS_IN_PROGRESS,
                'attempts' => DB::raw('attempts + 1'),
            ]);

        if (! $claimed) {
            // Not claimable: PRINTED, CANCELED, max_attempts exhausted, or
            // already claimed by another worker.
            return;
        }

        // ── 2. Fresh load after claim ──
        /** @var PrintJob|null $job */
        $job = PrintJob::with(['printer', 'printable'])->find($this->printJobId);

        if (! $job) {
            return;
        }

        // Restore locale from job creation time
        if ($job->locale) {
            app()->setLocale($job->locale);
        }

        $printer = $job->printer;
        $printable = $job->printable;

        // Voucher batch: render and send all vouchers in one job
        if ($job->job_kind === PrintJob::KIND_SALE_VOUCHER_BATCH) {
            $this->handleVoucherBatch($job, $printer, $registry);
            return;
        }

        // Cashier totals: render from payload, no printable model needed
        if ($job->job_kind === PrintJob::KIND_CASHIER_TOTALS) {
            $this->handleCashierTotals($job, $printer, $registry);
            return;
        }

        // Session totals: render from payload, no printable model needed
        if ($job->job_kind === PrintJob::KIND_SESSION_TOTALS) {
            $this->handleSessionTotals($job, $printer, $registry);
            return;
        }

        // Inventory movements: render from payload, no printable model needed
        if ($job->job_kind === PrintJob::KIND_INVENTORY_MOVEMENTS) {
            $this->handleInventoryMovements($job, $printer, $registry);
            return;
        }

        if (! $printer || ! $printable) {
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'last_error' => 'Missing printer or printable target',
            ]);

            return;
        }

        // ── 3. Render ESC/POS payload ──
        $documentConfig = $this->resolveDocumentConfig($printable);
        $renderer = new TicketRenderer(
            charWidth: $printer->print_char_width ?? 48,
            beginSpace: $printer->print_begin_space ?? 0,
            endSpace: $printer->print_end_space ?? 3,
            documentConfig: $documentConfig,
        );

        try {
            $payload = match (true) {
                $printable instanceof ProductionTicket => $renderer->renderProductionTicket($printable),
                $printable instanceof BillingDocument => $renderer->renderBill($printable),
                $printable instanceof SaleDocument => $renderer->renderSaleDocument($printable),
                default => throw new \LogicException('Unsupported printable: ' . $printable::class),
            };
        } catch (Throwable $e) {
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'last_error' => 'Render failure: ' . $e->getMessage(),
            ]);

            return;
        }

        // ── 5. Send via registry (non-blocking per-printer lock) ──
        // Determine copies: void slips always print exactly once.
        $copiesToSend = 1;
        $isVoidSlip = $printable instanceof ProductionTicket && $printable->is_void_slip;
        if ($documentConfig && $documentConfig->copies > 0 && ! $isVoidSlip) {
            $copiesToSend = $documentConfig->copies + 1; // original + extra copies
        }

        $result = $copiesToSend > 1
            ? $registry->sendBatch($printer, $payload, $copiesToSend)
            : $registry->send($printer, $payload);

        // ── 6. Lock contention → revert and re-dispatch ──
        if ($result->contended) {
            PrintJob::where('id', $job->id)
                ->where('status', PrintJob::STATUS_IN_PROGRESS)
                ->update([
                    'status' => PrintJob::STATUS_PENDING,
                    'attempts' => max(0, $job->attempts - 1),
                    'last_error' => $result->message,
                ]);

            static::dispatch($this->printJobId, $this->lockContentionAttempt + 1)
                ->onQueue('prints')
                ->delay(now()->addSeconds($this->contentionBackoff()));

            return;
        }

        // ── 7. Success ──
        if ($result->success) {
            $job->update([
                'status' => PrintJob::STATUS_PRINTED,
                'completed_at' => now(),
                'last_error' => null,
            ]);
            $printer->update(['health_status' => 'OK', 'last_seen_at' => now(), 'last_error' => null]);

            // ── Auto-trigger cash drawer if configured ──
            if ($documentConfig?->trigger_cash_drawer && $job->requested_by_user_id) {
                $cashierAssignment = CashierPrinterAssignment::where('user_id', $job->requested_by_user_id)
                    ->where('is_active', true)
                    ->first();
                if ($cashierAssignment) {
                    OpenCashDrawerJob::dispatch($cashierAssignment->printer_id, $job->requested_by_user_id)
                        ->onQueue('prints');
                }
            }

            if ($printable instanceof ProductionTicket) {
                $printable->update(['ticket_status' => 'PRINTED', 'printed_at' => now()]);
                Audit::record(
                    'PRODUCTION_TICKET_PRINTED',
                    "Ticket #{$printable->id} impresso com sucesso",
                    ['printer_id' => $printer->id, 'job_id' => $job->id],
                    [
                        'billing_group_id' => $printable->billing_group_id,
                        'service_session_id' => $printable->service_session_id,
                        'production_ticket_id' => $printable->id,
                        'actor_user_id' => $job->requested_by_user_id,
                    ],
                );
            } elseif ($printable instanceof BillingDocument) {
                $printable->update(['document_status' => 'PRINTED', 'printed_at' => now()]);
                Audit::record(
                    'BILL_PRINTED',
                    "Conta #{$printable->id} impressa com sucesso",
                    ['printer_id' => $printer->id, 'job_id' => $job->id],
                    [
                        'billing_group_id' => $printable->billing_group_id,
                        'billing_document_id' => $printable->id,
                        'actor_user_id' => $job->requested_by_user_id,
                    ],
                );
            } elseif ($printable instanceof SaleDocument) {
                $printable->update(['document_status' => 'PRINTED', 'printed_at' => now()]);

                $eventType = $printable->document_type === SaleDocument::TYPE_RECEIPT
                    ? 'SALE_RECEIPT_PRINTED'
                    : 'SALE_VOUCHER_PRINTED';

                Audit::record(
                    $eventType,
                    "Sale document #{$printable->id} printed successfully",
                    ['printer_id' => $printer->id, 'job_id' => $job->id],
                    [
                        'service_session_id' => $printable->sale?->service_session_id,
                        'sale_id' => $printable->sale_id,
                        'sale_document_id' => $printable->id,
                        'actor_user_id' => $job->requested_by_user_id,
                    ],
                );
            }

            return;
        }

        // ── 8. Transport failure ──
        $isFinalAttempt = $job->attempts >= $job->max_attempts;
        $backoffSeconds = $this->transportBackoff($job->attempts);

        $job->update([
            'status' => PrintJob::STATUS_FAILED,
            'last_error' => $result->message,
            'next_attempt_at' => now()->addSeconds($backoffSeconds),
        ]);
        $printer->update(['health_status' => 'UNREACHABLE', 'last_error' => $result->message]);

        // Only mark ProductionTicket as FAILED on the final attempt
        if ($printable instanceof ProductionTicket && $isFinalAttempt) {
            $printable->update(['ticket_status' => 'FAILED']);
            Audit::record(
                'PRODUCTION_TICKET_FAILED',
                "Ticket #{$printable->id} falhou ao imprimir: {$result->message}",
                ['printer_id' => $printer->id, 'job_id' => $job->id, 'error' => $result->message],
                [
                    'billing_group_id' => $printable->billing_group_id,
                    'service_session_id' => $printable->service_session_id,
                    'production_ticket_id' => $printable->id,
                    'actor_user_id' => $job->requested_by_user_id,
                ],
            );
        }

        if ($isFinalAttempt) {
            Audit::record(
                'PRINT_JOB_MAX_ATTEMPTS',
                "PrintJob #{$job->id} reached max attempts ({$job->max_attempts})",
                ['printer_id' => $job->printer_id, 'job_id' => $job->id],
                [
                    'actor_user_id' => $job->requested_by_user_id,
                    'production_ticket_id' => $printable instanceof ProductionTicket ? $printable->id : null,
                ],
            );
        }

        // Auto-retry: re-dispatch with transport backoff delay
        if (! $isFinalAttempt) {
            static::dispatch($this->printJobId, $this->lockContentionAttempt)
                ->onQueue('prints')
                ->delay(now()->addSeconds($backoffSeconds));
        }
    }

    /**
     * Handle a batch of sale vouchers: render each document and send via
     * sendBatch for lock-coherent, non-interleaving output.
     */
    private function handleVoucherBatch(PrintJob $job, Printer $printer, PrinterAdapterRegistry $registry): void
    {
        $documentIds = $job->payload['document_ids'] ?? [];
        if (empty($documentIds)) {
            $job->update(['status' => PrintJob::STATUS_FAILED, 'last_error' => 'No document IDs in batch payload']);
            return;
        }

        $documents = SaleDocument::whereIn('id', $documentIds)->get();
        $payloads = [];
        $renderer = new TicketRenderer(
            charWidth: $printer->print_char_width ?? 48,
            beginSpace: $printer->print_begin_space ?? 0,
            endSpace: 2,
        );

        foreach ($documents as $document) {
            try {
                $payloads[] = $renderer->renderSaleDocument($document);
            } catch (Throwable $e) {
                $job->update([
                    'status' => PrintJob::STATUS_FAILED,
                    'last_error' => 'Render failure for doc #'.$document->id.': '.$e->getMessage(),
                ]);
                return;
            }
        }

        $result = $registry->sendPayloadBatch($printer, $payloads);

        if ($result->contended) {
            PrintJob::where('id', $job->id)
                ->where('status', PrintJob::STATUS_IN_PROGRESS)
                ->update([
                    'status' => PrintJob::STATUS_PENDING,
                    'attempts' => max(0, $job->attempts - 1),
                    'last_error' => $result->message,
                ]);

            static::dispatch($job->id, $this->lockContentionAttempt + 1)
                ->onQueue('prints')
                ->delay(now()->addSeconds($this->contentionBackoff()));
            return;
        }

        if ($result->success) {
            $job->update([
                'status' => PrintJob::STATUS_PRINTED,
                'completed_at' => now(),
                'last_error' => null,
            ]);
            $printer->update(['health_status' => 'OK', 'last_seen_at' => now(), 'last_error' => null]);

            foreach ($documents as $document) {
                $document->update(['document_status' => 'PRINTED', 'printed_at' => now()]);
            }
            return;
        }

        // Transport failure
        $isFinalAttempt = $job->attempts >= $job->max_attempts;
        $backoffSeconds = $this->transportBackoff($job->attempts);

        $job->update([
            'status' => PrintJob::STATUS_FAILED,
            'last_error' => $result->message,
            'next_attempt_at' => now()->addSeconds($backoffSeconds),
        ]);
        $printer->update(['health_status' => 'UNREACHABLE', 'last_error' => $result->message]);

        if (! $isFinalAttempt) {
            static::dispatch($job->id, $this->lockContentionAttempt)
                ->onQueue('prints')
                ->delay(now()->addSeconds($backoffSeconds));
        }
    }

    /**
     * Handle a cashier totals print job. Renders from payload data
     * (no printable model), sends to the cashier's assigned printer.
     */
    private function handleCashierTotals(PrintJob $job, Printer $printer, PrinterAdapterRegistry $registry): void
    {
        $totals = $job->payload['totals'] ?? [];

        if (empty($totals)) {
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'last_error' => 'No totals data in job payload',
            ]);
            return;
        }

        // Resolve document config
        $documentConfig = DocumentPrintConfig::firstOrCreate(
            ['document_type' => PrinterRoute::DOC_CASHIER_TOTALS, 'fulfillment_route' => null],
            ['group_items' => false, 'ignore_variants' => false, 'ignore_modifiers' => false, 'ignore_item_notes' => false, 'trigger_cash_drawer' => false],
        );

        $renderer = new TicketRenderer(
            charWidth: $printer->print_char_width ?? 48,
            beginSpace: $printer->print_begin_space ?? 0,
            endSpace: $printer->print_end_space ?? 3,
            documentConfig: $documentConfig,
        );

        try {
            $payload = $renderer->renderCashierTotals($totals);
        } catch (\Throwable $e) {
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'last_error' => 'Render failure: '.$e->getMessage(),
            ]);
            return;
        }

        // Copies
        $copiesToSend = 1;
        if ($documentConfig && $documentConfig->copies > 0) {
            $copiesToSend = $documentConfig->copies + 1;
        }

        $result = $copiesToSend > 1
            ? $registry->sendBatch($printer, $payload, $copiesToSend)
            : $registry->send($printer, $payload);

        // Lock contention → revert and re-dispatch
        if ($result->contended) {
            PrintJob::where('id', $job->id)
                ->where('status', PrintJob::STATUS_IN_PROGRESS)
                ->update([
                    'status' => PrintJob::STATUS_PENDING,
                    'attempts' => max(0, $job->attempts - 1),
                    'last_error' => $result->message,
                ]);

            static::dispatch($job->id, $this->lockContentionAttempt + 1)
                ->onQueue('prints')
                ->delay(now()->addSeconds($this->contentionBackoff()));

            return;
        }

        // Success
        if ($result->success) {
            $job->update([
                'status' => PrintJob::STATUS_PRINTED,
                'completed_at' => now(),
                'last_error' => null,
            ]);
            $printer->update(['health_status' => 'OK', 'last_seen_at' => now(), 'last_error' => null]);

            // Auto-trigger cash drawer if configured
            if ($documentConfig?->trigger_cash_drawer && $job->requested_by_user_id) {
                $cashierAssignment = CashierPrinterAssignment::where('user_id', $job->requested_by_user_id)
                    ->where('is_active', true)
                    ->first();
                if ($cashierAssignment) {
                    OpenCashDrawerJob::dispatch($cashierAssignment->printer_id, $job->requested_by_user_id)
                        ->onQueue('prints');
                }
            }

            Audit::record(
                'CASHIER_TOTALS_PRINTED',
                'Cashier totals printed successfully',
                ['printer_id' => $printer->id, 'job_id' => $job->id],
                ['actor_user_id' => $job->requested_by_user_id],
            );

            return;
        }

        // Transport failure
        $isFinalAttempt = $job->attempts >= $job->max_attempts;
        $backoffSeconds = $this->transportBackoff($job->attempts);

        $job->update([
            'status' => PrintJob::STATUS_FAILED,
            'last_error' => $result->message,
            'next_attempt_at' => now()->addSeconds($backoffSeconds),
        ]);
        $printer->update(['health_status' => 'UNREACHABLE', 'last_error' => $result->message]);

        if ($isFinalAttempt) {
            Audit::record(
                'PRINT_JOB_MAX_ATTEMPTS',
                "PrintJob #{$job->id} reached max attempts ({$job->max_attempts})",
                ['printer_id' => $job->printer_id, 'job_id' => $job->id],
                ['actor_user_id' => $job->requested_by_user_id],
            );
        }

        // Auto-retry
        if (! $isFinalAttempt) {
            static::dispatch($job->id, $this->lockContentionAttempt)
                ->onQueue('prints')
                ->delay(now()->addSeconds($backoffSeconds));
        }
    }

    /**
     * Handle a session totals print job. Renders from payload data
     * (no printable model), sends to the assigned printer.
     */
    private function handleSessionTotals(PrintJob $job, Printer $printer, PrinterAdapterRegistry $registry): void
    {
        $totals = $job->payload['totals'] ?? [];

        if (empty($totals)) {
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'last_error' => 'No totals data in job payload',
            ]);
            return;
        }

        $documentConfig = DocumentPrintConfig::firstOrCreate(
            ['document_type' => PrinterRoute::DOC_SESSION_TOTALS, 'fulfillment_route' => null],
            ['group_items' => false, 'ignore_variants' => false, 'ignore_modifiers' => false, 'ignore_item_notes' => false, 'trigger_cash_drawer' => false],
        );

        $renderer = new TicketRenderer(
            charWidth: $printer->print_char_width ?? 48,
            beginSpace: $printer->print_begin_space ?? 0,
            endSpace: $printer->print_end_space ?? 3,
            documentConfig: $documentConfig,
        );

        try {
            $payload = $renderer->renderSessionTotals($totals);
        } catch (\Throwable $e) {
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'last_error' => 'Render failure: ' . $e->getMessage(),
            ]);
            return;
        }

        $copiesToSend = 1;
        if ($documentConfig && $documentConfig->copies > 0) {
            $copiesToSend = $documentConfig->copies + 1;
        }

        $result = $copiesToSend > 1
            ? $registry->sendBatch($printer, $payload, $copiesToSend)
            : $registry->send($printer, $payload);

        if ($result->contended) {
            PrintJob::where('id', $job->id)
                ->where('status', PrintJob::STATUS_IN_PROGRESS)
                ->update([
                    'status' => PrintJob::STATUS_PENDING,
                    'attempts' => max(0, $job->attempts - 1),
                    'last_error' => $result->message,
                ]);

            static::dispatch($job->id, $this->lockContentionAttempt + 1)
                ->onQueue('prints')
                ->delay(now()->addSeconds($this->contentionBackoff()));

            return;
        }

        if ($result->success) {
            $job->update([
                'status' => PrintJob::STATUS_PRINTED,
                'completed_at' => now(),
                'last_error' => null,
            ]);
            $printer->update(['health_status' => 'OK', 'last_seen_at' => now(), 'last_error' => null]);

            if ($documentConfig?->trigger_cash_drawer && $job->requested_by_user_id) {
                $cashierAssignment = CashierPrinterAssignment::where('user_id', $job->requested_by_user_id)
                    ->where('is_active', true)
                    ->first();
                if ($cashierAssignment) {
                    OpenCashDrawerJob::dispatch($cashierAssignment->printer_id, $job->requested_by_user_id)
                        ->onQueue('prints');
                }
            }

            Audit::record(
                'SESSION_TOTALS_PRINTED',
                'Session totals printed successfully',
                ['printer_id' => $printer->id, 'job_id' => $job->id],
                ['actor_user_id' => $job->requested_by_user_id],
            );

            return;
        }

        // Transport failure
        $isFinalAttempt = $job->attempts >= $job->max_attempts;
        $backoffSeconds = $this->transportBackoff($job->attempts);

        $job->update([
            'status' => PrintJob::STATUS_FAILED,
            'last_error' => $result->message,
            'next_attempt_at' => now()->addSeconds($backoffSeconds),
        ]);
        $printer->update(['health_status' => 'UNREACHABLE', 'last_error' => $result->message]);

        if ($isFinalAttempt) {
            Audit::record(
                'PRINT_JOB_MAX_ATTEMPTS',
                "PrintJob #{$job->id} reached max attempts ({$job->max_attempts})",
                ['printer_id' => $job->printer_id, 'job_id' => $job->id],
                ['actor_user_id' => $job->requested_by_user_id],
            );
        }

        if (! $isFinalAttempt) {
            static::dispatch($job->id, $this->lockContentionAttempt)
                ->onQueue('prints')
                ->delay(now()->addSeconds($backoffSeconds));
        }
    }

    /**
     * Handle an inventory movements print job. Renders from payload data
     * (no printable model), sends to the assigned printer.
     */
    private function handleInventoryMovements(PrintJob $job, Printer $printer, PrinterAdapterRegistry $registry): void
    {
        $totals = $job->payload['totals'] ?? [];

        if (empty($totals)) {
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'last_error' => 'No inventory data in job payload',
            ]);
            return;
        }

        $documentConfig = DocumentPrintConfig::firstOrCreate(
            ['document_type' => PrinterRoute::DOC_INVENTORY_MOVEMENTS, 'fulfillment_route' => null],
            ['group_items' => true, 'ignore_variants' => false, 'ignore_modifiers' => true, 'ignore_item_notes' => true, 'trigger_cash_drawer' => false],
        );

        $renderer = new TicketRenderer(
            charWidth: $printer->print_char_width ?? 48,
            beginSpace: $printer->print_begin_space ?? 0,
            endSpace: $printer->print_end_space ?? 3,
            documentConfig: $documentConfig,
        );

        try {
            $payload = $renderer->renderInventoryMovements($totals);
        } catch (\Throwable $e) {
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'last_error' => 'Render failure: ' . $e->getMessage(),
            ]);
            return;
        }

        $copiesToSend = 1;
        if ($documentConfig && $documentConfig->copies > 0) {
            $copiesToSend = $documentConfig->copies + 1;
        }

        $result = $copiesToSend > 1
            ? $registry->sendBatch($printer, $payload, $copiesToSend)
            : $registry->send($printer, $payload);

        if ($result->contended) {
            PrintJob::where('id', $job->id)
                ->where('status', PrintJob::STATUS_IN_PROGRESS)
                ->update([
                    'status' => PrintJob::STATUS_PENDING,
                    'attempts' => max(0, $job->attempts - 1),
                    'last_error' => $result->message,
                ]);

            static::dispatch($job->id, $this->lockContentionAttempt + 1)
                ->onQueue('prints')
                ->delay(now()->addSeconds($this->contentionBackoff()));

            return;
        }

        if ($result->success) {
            $job->update([
                'status' => PrintJob::STATUS_PRINTED,
                'completed_at' => now(),
                'last_error' => null,
            ]);
            $printer->update(['health_status' => 'OK', 'last_seen_at' => now(), 'last_error' => null]);

            if ($documentConfig?->trigger_cash_drawer && $job->requested_by_user_id) {
                $cashierAssignment = CashierPrinterAssignment::where('user_id', $job->requested_by_user_id)
                    ->where('is_active', true)
                    ->first();
                if ($cashierAssignment) {
                    OpenCashDrawerJob::dispatch($cashierAssignment->printer_id, $job->requested_by_user_id)
                        ->onQueue('prints');
                }
            }

            Audit::record(
                'INVENTORY_MOVEMENTS_PRINTED',
                'Inventory movements printed successfully',
                ['printer_id' => $printer->id, 'job_id' => $job->id],
                ['actor_user_id' => $job->requested_by_user_id],
            );

            return;
        }

        // Transport failure
        $isFinalAttempt = $job->attempts >= $job->max_attempts;
        $backoffSeconds = $this->transportBackoff($job->attempts);

        $job->update([
            'status' => PrintJob::STATUS_FAILED,
            'last_error' => $result->message,
            'next_attempt_at' => now()->addSeconds($backoffSeconds),
        ]);
        $printer->update(['health_status' => 'UNREACHABLE', 'last_error' => $result->message]);

        if ($isFinalAttempt) {
            Audit::record(
                'PRINT_JOB_MAX_ATTEMPTS',
                "PrintJob #{$job->id} reached max attempts ({$job->max_attempts})",
                ['printer_id' => $job->printer_id, 'job_id' => $job->id],
                ['actor_user_id' => $job->requested_by_user_id],
            );
        }

        if (! $isFinalAttempt) {
            static::dispatch($job->id, $this->lockContentionAttempt)
                ->onQueue('prints')
                ->delay(now()->addSeconds($backoffSeconds));
        }
    }

    /**
     * Called by Laravel when the job fails with an unhandled exception
     * (e.g. worker killed by --timeout, fatal PHP error, SIGKILL).
     *
     * Reverts the PrintJob from IN_PROGRESS to FAILED so it doesn't
     * remain stuck forever.
     */
    public function failed(Throwable $e): void
    {
        PrintJob::where('id', $this->printJobId)
            ->where('status', PrintJob::STATUS_IN_PROGRESS)
            ->update([
                'status' => PrintJob::STATUS_FAILED,
                'last_error' => 'Worker failure: ' . ($e->getMessage() ?: 'process terminated'),
            ]);
    }

    /**
     * Calculate contention backoff delay with ±20% jitter.
     *
     * Sequence: ~1s → ~2s → ~4s → ~8s → ~10s (capped at 10s).
     *
     * @return int Delay in seconds
     */
    private function contentionBackoff(): int
    {
        $base = match (true) {
            $this->lockContentionAttempt <= 0 => 1,
            $this->lockContentionAttempt === 1 => 2,
            $this->lockContentionAttempt === 2 => 4,
            $this->lockContentionAttempt === 3 => 8,
            default => 10,
        };

        return $this->applyJitter($base);
    }

    /**
     * Calculate transport failure backoff delay with ±20% jitter.
     *
     * Sequence: ~3s → ~6s → ~10s (capped at 10s).
     *
     * @param  int  $attempt  Current attempt number (1-based, AFTER increment)
     * @return int  Delay in seconds
     */
    private function transportBackoff(int $attempt): int
    {
        $base = min(3 * (int) pow(2, $attempt - 1), 10);

        return $this->applyJitter($base);
    }

    /**
     * Apply ±20% random jitter to a base delay to prevent thundering herd.
     */
    private function applyJitter(int $base): int
    {
        $jitter = (int) round($base * 0.2);

        return max(1, $base + mt_rand(-$jitter, $jitter));
    }

    /**
     * Resolve the DocumentPrintConfig for a given printable, matching
     * by document type and (for production tickets) fulfillment route.
     *
     * Void slips inherit the route from the original item so they use
     * the same config as the original production ticket.
     *
     * BILL configs are lazily created on first print with safe defaults.
     */
    private function resolveDocumentConfig($printable): ?DocumentPrintConfig
    {
        if ($printable instanceof ProductionTicket) {
            return DocumentPrintConfig::where('document_type', PrinterRoute::DOC_PRODUCTION_TICKET)
                ->where('fulfillment_route', $printable->effectiveFulfillmentRoute())
                ->where('is_active', true)
                ->first();
        }

        if ($printable instanceof BillingDocument) {
            return DocumentPrintConfig::firstOrCreate(
                ['document_type' => PrinterRoute::DOC_BILL, 'fulfillment_route' => null],
                ['group_items' => false, 'ignore_variants' => false, 'ignore_modifiers' => false, 'ignore_item_notes' => true],
            );
        }

        if ($printable instanceof SaleDocument) {
            return DocumentPrintConfig::firstOrCreate(
                [
                    'document_type' => $printable->document_type === SaleDocument::TYPE_RECEIPT
                        ? PrinterRoute::DOC_SALE_RECEIPT
                        : PrinterRoute::DOC_SALE_VOUCHER,
                    'fulfillment_route' => null,
                ],
                [
                    'group_items' => $printable->document_type === SaleDocument::TYPE_RECEIPT,
                    'ignore_variants' => true,
                    'ignore_modifiers' => true,
                    'ignore_item_notes' => true,
                ],
            );
        }

        return null;
    }
}
