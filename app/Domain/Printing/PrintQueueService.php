<?php

namespace App\Domain\Printing;

use App\Jobs\DispatchPrintJob;
use App\Models\BillingDocument;
use App\Models\PrintJob;
use App\Models\ProductionTicket;
use App\Models\User;

class PrintQueueService
{
    public function enqueueProductionTicket(ProductionTicket $ticket, ?User $actor = null): PrintJob
    {
        $job = PrintJob::create([
            'job_kind'             => PrintJob::KIND_PRODUCTION_TICKET,
            'printable_type'       => ProductionTicket::class,
            'printable_id'         => $ticket->id,
            'printer_id'           => $ticket->printer_id,
            'status'               => PrintJob::STATUS_PENDING,
            'attempts'             => 0,
            'max_attempts'         => 3,
            'requested_by_user_id' => $actor?->id,
        ]);

        DispatchPrintJob::dispatch($job->id)->onQueue('prints');

        return $job;
    }

    public function enqueueBill(BillingDocument $bill, ?User $actor = null): PrintJob
    {
        $job = PrintJob::create([
            'job_kind'             => PrintJob::KIND_BILL,
            'printable_type'       => BillingDocument::class,
            'printable_id'         => $bill->id,
            'printer_id'           => $bill->printer_id,
            'status'               => PrintJob::STATUS_PENDING,
            'attempts'             => 0,
            'max_attempts'         => 3,
            'requested_by_user_id' => $actor?->id,
        ]);

        DispatchPrintJob::dispatch($job->id)->onQueue('prints');

        return $job;
    }

    /**
     * Re-queue a failed job. Returns true if a dispatch was scheduled.
     */
    public function retry(PrintJob $job, ?User $actor = null): bool
    {
        if (! in_array($job->status, [PrintJob::STATUS_FAILED, PrintJob::STATUS_CANCELED], true)) {
            return false;
        }
        $job->update([
            'status'        => PrintJob::STATUS_PENDING,
            'last_error'    => null,
            'next_attempt_at' => now(),
        ]);
        DispatchPrintJob::dispatch($job->id)->onQueue('prints');
        return true;
    }
}
