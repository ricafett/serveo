<?php

namespace App\Jobs;

use App\Domain\Audit\Audit;
use App\Domain\Printing\PrinterAdapterRegistry;
use App\Domain\Printing\TicketRenderer;
use App\Models\BillingDocument;
use App\Models\DocumentPrintConfig;
use App\Models\PrintJob;
use App\Models\PrinterRoute;
use App\Models\ProductionTicket;
use App\Models\SaleDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sends one queued PrintJob to its target printer using the configured adapter.
 *
 * Implements automatic retry with exponential backoff:
 *   - On transient failure, re-dispatches itself after a delay.
 *   - After max_attempts, marks the job permanently FAILED and emits an audit event.
 *   - The idempotency guard (STATUS_PRINTED / STATUS_CANCELED) prevents double-printing
 *     if a job is re-queued after the adapter succeeded but the DB update failed.
 *
 * Failure semantics:
 *   - Transport errors update the PrintJob row (status FAILED, last_error set,
 *     attempts++, next_attempt_at), keeping the job visible and retryable.
 *   - Production tickets are only marked FAILED on the final attempt.
 *   - We do not throw on transport failures; the job is considered handled and
 *     self-retries via re-dispatch.
 */
class DispatchPrintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // we manage retries via PrintJob.attempts

    public function __construct(public int $printJobId) {}

    public function handle(PrinterAdapterRegistry $registry): void
    {
        /** @var PrintJob|null $job */
        $job = PrintJob::with(['printer', 'printable'])->find($this->printJobId);
        if (! $job) {
            return;
        }

        // Restore the locale that was active when the print job was created,
        // so printed output uses the correct language regardless of queue worker defaults.
        if ($job->locale) {
            app()->setLocale($job->locale);
        }

        // Idempotency guard: already done or canceled
        if ($job->status === PrintJob::STATUS_PRINTED || $job->status === PrintJob::STATUS_CANCELED) {
            return;
        }

        // Restore the locale that was active when the print job was created,
        // so printed output uses the correct language regardless of queue worker defaults.
        if ($job->locale) {
            app()->setLocale($job->locale);
        }
        if ($job->status === PrintJob::STATUS_PRINTED || $job->status === PrintJob::STATUS_CANCELED) {
            return;
        }

        // Guard: max attempts exceeded → permanently FAILED
        if ($job->attempts >= $job->max_attempts) {
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'last_error' => 'Max attempts reached',
            ]);

            if ($job->printable instanceof ProductionTicket) {
                $job->printable->update(['ticket_status' => 'FAILED']);
            }

            Audit::record(
                'PRINT_JOB_MAX_ATTEMPTS',
                "PrintJob #{$job->id} reached max attempts ({$job->max_attempts})",
                ['printer_id' => $job->printer_id, 'job_id' => $job->id],
                [
                    'actor_user_id' => $job->requested_by_user_id,
                    'production_ticket_id' => $job->printable instanceof ProductionTicket ? $job->printable->id : null,
                ],
            );

            return;
        }

        $job->update([
            'status' => PrintJob::STATUS_IN_PROGRESS,
            'attempts' => $job->attempts + 1,
        ]);

        $printer = $job->printer;
        $printable = $job->printable;

        if (! $printer || ! $printable) {
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'last_error' => 'Missing printer or printable target',
            ]);

            return;
        }

        // Create TicketRenderer with printer-specific configuration
        $renderer = new TicketRenderer(
            charWidth: $printer->print_char_width ?? 48,
            beginSpace: $printer->print_begin_space ?? 0,
            endSpace: $printer->print_end_space ?? 3,
            documentConfig: $this->resolveDocumentConfig($printable),
        );

        try {
            $payload = match (true) {
                $printable instanceof ProductionTicket => $renderer->renderProductionTicket($printable),
                $printable instanceof BillingDocument => $renderer->renderBill($printable),
                $printable instanceof SaleDocument => $renderer->renderSaleDocument($printable),
                default => throw new \LogicException('Unsupported printable: '.$printable::class),
            };
        } catch (Throwable $e) {
            $job->update([
                'status' => PrintJob::STATUS_FAILED,
                'last_error' => 'Render failure: '.$e->getMessage(),
            ]);

            return;
        }

        $adapter = $registry->for($printer);
        $result = $adapter->send($printer, $payload);

        if ($result->success) {
            $job->update([
                'status' => PrintJob::STATUS_PRINTED,
                'completed_at' => now(),
                'last_error' => null,
            ]);
            $printer->update(['health_status' => 'OK', 'last_seen_at' => now(), 'last_error' => null]);

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

        // --- Transport failure ---
        $isFinalAttempt = $job->attempts >= $job->max_attempts;
        $backoffSeconds = $this->calculateBackoff($job->attempts);

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

        // Auto-retry: re-dispatch with exponential backoff delay
        if (! $isFinalAttempt) {
            static::dispatch($job->id)
                ->onQueue('prints')
                ->delay(now()->addSeconds($backoffSeconds));
        }
    }

    /**
     * Calculate exponential backoff delay for a given attempt number.
     *
     * Sequence: 3s, 6s, 10s (capped at 10s).
     *
     * @param  int  $attempt  Current attempt number (1-based, AFTER increment)
     * @return int  Delay in seconds
     */
    private function calculateBackoff(int $attempt): int
    {
        return min(3 * (int) pow(2, $attempt - 1), 10);
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
                ['group_items' => false, 'ignore_variants' => false, 'ignore_modifiers' => false],
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
                ],
            );
        }

        return null;
    }
}
