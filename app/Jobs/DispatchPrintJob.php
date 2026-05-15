<?php

namespace App\Jobs;

use App\Domain\Audit\Audit;
use App\Domain\Printing\PrinterAdapterRegistry;
use App\Domain\Printing\TicketRenderer;
use App\Models\BillingDocument;
use App\Models\PrintJob;
use App\Models\ProductionTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sends one queued PrintJob to its target printer using the configured adapter.
 *
 * Failure semantics:
 *   - Transport errors update the PrintJob row (status FAILED, last_error set,
 *     attempts++), keeping the job visible and retryable from the UI/CLI.
 *   - We do not throw on transport failures; the job is considered handled and
 *     the operator can retry.
 */
class DispatchPrintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // we manage retries via PrintJob.attempts

    public function __construct(public int $printJobId) {}

    public function handle(PrinterAdapterRegistry $registry, TicketRenderer $renderer): void
    {
        /** @var PrintJob|null $job */
        $job = PrintJob::with(['printer', 'printable'])->find($this->printJobId);
        if (! $job) {
            return;
        }

        if ($job->status === PrintJob::STATUS_PRINTED || $job->status === PrintJob::STATUS_CANCELED) {
            return;
        }

        $job->update([
            'status'   => PrintJob::STATUS_IN_PROGRESS,
            'attempts' => $job->attempts + 1,
        ]);

        $printer  = $job->printer;
        $printable = $job->printable;

        if (! $printer || ! $printable) {
            $job->update([
                'status'     => PrintJob::STATUS_FAILED,
                'last_error' => 'Missing printer or printable target',
            ]);
            return;
        }

        try {
            $payload = match (true) {
                $printable instanceof ProductionTicket => $renderer->renderProductionTicket($printable),
                $printable instanceof BillingDocument  => $renderer->renderBill($printable),
                default => throw new \LogicException('Unsupported printable: '.$printable::class),
            };
        } catch (Throwable $e) {
            $job->update([
                'status'     => PrintJob::STATUS_FAILED,
                'last_error' => 'Render failure: '.$e->getMessage(),
            ]);
            return;
        }

        $adapter = $registry->for($printer);
        $result  = $adapter->send($printer, $payload);

        if ($result->success) {
            $job->update([
                'status'       => PrintJob::STATUS_PRINTED,
                'completed_at' => now(),
                'last_error'   => null,
            ]);
            $printer->update(['health_status' => 'OK', 'last_seen_at' => now(), 'last_error' => null]);

            if ($printable instanceof ProductionTicket) {
                $printable->update(['ticket_status' => 'PRINTED', 'printed_at' => now()]);
                Audit::record(
                    'PRODUCTION_TICKET_PRINTED',
                    "Ticket #{$printable->id} impresso com sucesso",
                    ['printer_id' => $printer->id, 'job_id' => $job->id],
                    [
                        'billing_group_id'     => $printable->billing_group_id,
                        'service_session_id'   => $printable->service_session_id,
                        'production_ticket_id' => $printable->id,
                        'actor_user_id'        => $job->requested_by_user_id,
                    ],
                );
            } elseif ($printable instanceof BillingDocument) {
                $printable->update(['document_status' => 'PRINTED', 'printed_at' => now()]);
                Audit::record(
                    'BILL_PRINTED',
                    "Conta #{$printable->id} impressa com sucesso",
                    ['printer_id' => $printer->id, 'job_id' => $job->id],
                    [
                        'billing_group_id'    => $printable->billing_group_id,
                        'billing_document_id' => $printable->id,
                        'actor_user_id'       => $job->requested_by_user_id,
                    ],
                );
            }
            return;
        }

        $job->update([
            'status'     => PrintJob::STATUS_FAILED,
            'last_error' => $result->message,
        ]);
        $printer->update(['health_status' => 'UNREACHABLE', 'last_error' => $result->message]);

        if ($printable instanceof ProductionTicket) {
            $printable->update(['ticket_status' => 'FAILED']);
            Audit::record(
                'PRODUCTION_TICKET_FAILED',
                "Ticket #{$printable->id} falhou ao imprimir: {$result->message}",
                ['printer_id' => $printer->id, 'job_id' => $job->id, 'error' => $result->message],
                [
                    'billing_group_id'     => $printable->billing_group_id,
                    'service_session_id'   => $printable->service_session_id,
                    'production_ticket_id' => $printable->id,
                    'actor_user_id'        => $job->requested_by_user_id,
                ],
            );
        }
    }
}
