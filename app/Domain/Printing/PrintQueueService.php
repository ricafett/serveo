<?php

namespace App\Domain\Printing;

use App\Jobs\DispatchPrintJob;
use App\Models\BillingDocument;
use App\Models\PrintJob;
use App\Models\ProductionTicket;
use App\Models\SaleDocument;
use App\Models\User;

class PrintQueueService
{
    public function enqueueProductionTicket(ProductionTicket $ticket, ?User $actor = null): PrintJob
    {
        $job = PrintJob::create([
            'job_kind' => PrintJob::KIND_PRODUCTION_TICKET,
            'printable_type' => ProductionTicket::class,
            'printable_id' => $ticket->id,
            'printer_id' => $ticket->printer_id,
            'status' => PrintJob::STATUS_PENDING,
            'attempts' => 0,
            'max_attempts' => 4,
            'requested_by_user_id' => $actor?->id,
            'locale' => config('app.locale', 'pt-PT'),
        ]);

        DispatchPrintJob::dispatch($job->id)->onQueue('prints')->afterCommit();

        return $job;
    }

    public function enqueueBill(BillingDocument $bill, ?User $actor = null): PrintJob
    {
        $job = PrintJob::create([
            'job_kind' => PrintJob::KIND_BILL,
            'printable_type' => BillingDocument::class,
            'printable_id' => $bill->id,
            'printer_id' => $bill->printer_id,
            'status' => PrintJob::STATUS_PENDING,
            'attempts' => 0,
            'max_attempts' => 4,
            'requested_by_user_id' => $actor?->id,
            'locale' => config('app.locale', 'pt-PT'),
        ]);

        DispatchPrintJob::dispatch($job->id)->onQueue('prints')->afterCommit();

        return $job;
    }

    public function enqueueSaleVoucher(SaleDocument $document, ?User $actor = null): PrintJob
    {
        $job = PrintJob::create([
            'job_kind' => PrintJob::KIND_SALE_VOUCHER,
            'printable_type' => SaleDocument::class,
            'printable_id' => $document->id,
            'printer_id' => $document->printer_id,
            'status' => PrintJob::STATUS_PENDING,
            'attempts' => 0,
            'max_attempts' => 4,
            'requested_by_user_id' => $actor?->id,
            'locale' => config('app.locale', 'pt-PT'),
        ]);

        DispatchPrintJob::dispatch($job->id)->onQueue('prints')->afterCommit();

        return $job;
    }

    public function enqueueSaleReceipt(SaleDocument $document, ?User $actor = null): PrintJob
    {
        $job = PrintJob::create([
            'job_kind' => PrintJob::KIND_SALE_RECEIPT,
            'printable_type' => SaleDocument::class,
            'printable_id' => $document->id,
            'printer_id' => $document->printer_id,
            'status' => PrintJob::STATUS_PENDING,
            'attempts' => 0,
            'max_attempts' => 4,
            'requested_by_user_id' => $actor?->id,
            'locale' => config('app.locale', 'pt-PT'),
        ]);

        DispatchPrintJob::dispatch($job->id)->onQueue('prints')->afterCommit();

        return $job;
    }

    /**
     * Batch-enqueue all vouchers for a single sale into one PrintJob.
     * All document IDs are stored in the payload column and rendered
     * sequentially within a single per-printer lock, preventing interleaving.
     *
     * @param  int[]  $documentIds
     */
    public function enqueueSaleVoucherBatch(int $printerId, array $documentIds, ?User $actor = null): PrintJob
    {
        $job = PrintJob::create([
            'job_kind' => PrintJob::KIND_SALE_VOUCHER_BATCH,
            'printable_type' => SaleDocument::class,
            'printable_id' => 0, // batch job — real document IDs are in payload
            'printer_id' => $printerId,
            'status' => PrintJob::STATUS_PENDING,
            'attempts' => 0,
            'max_attempts' => 4,
            'requested_by_user_id' => $actor?->id,
            'locale' => config('app.locale', 'pt-PT'),
            'payload' => ['document_ids' => $documentIds],
        ]);

        DispatchPrintJob::dispatch($job->id)->onQueue('prints')->afterCommit();

        return $job;
    }

    /**
     * Enqueue a cashier totals print job. The totals data is stored in the
     * payload column; no persistent document model is created.
     */
    public function enqueueCashierTotals(int $printerId, array $totals, ?User $actor = null): PrintJob
    {
        $job = PrintJob::create([
            'job_kind'       => PrintJob::KIND_CASHIER_TOTALS,
            'printable_type' => PrintJob::class,
            'printable_id'   => 0,
            'printer_id'     => $printerId,
            'status'         => PrintJob::STATUS_PENDING,
            'attempts'       => 0,
            'max_attempts'   => 4,
            'requested_by_user_id' => $actor?->id,
            'locale'         => config('app.locale', 'pt-PT'),
            'payload'        => ['totals' => $totals],
        ]);

        DispatchPrintJob::dispatch($job->id)->onQueue('prints')->afterCommit();

        return $job;
    }

    /**
     * Re-queue a failed job (manual admin retry). Resets the attempt counter
     * so the auto-retry loop gets a fresh start. Returns true if a dispatch
     * was scheduled.
     */
    public function retry(PrintJob $job, ?User $actor = null): bool
    {
        if (! in_array($job->status, [PrintJob::STATUS_FAILED, PrintJob::STATUS_CANCELED], true)) {
            return false;
        }
        $job->update([
            'status' => PrintJob::STATUS_PENDING,
            'last_error' => null,
            'attempts' => 0,
            'next_attempt_at' => now(),
        ]);
        DispatchPrintJob::dispatch($job->id)->onQueue('prints')->afterCommit();

        return true;
    }

    /**
     * Batch retry multiple print jobs. Only jobs in FAILED or CANCELED
     * state are eligible; others are silently skipped.
     *
     * @param  int[]  $printJobIds
     * @return array{success: int, skipped: int}
     */
    public function retryBatch(array $printJobIds, ?User $actor = null): array
    {
        $results = ['success' => 0, 'skipped' => 0];

        $jobs = PrintJob::whereIn('id', $printJobIds)
            ->whereIn('status', [PrintJob::STATUS_FAILED, PrintJob::STATUS_CANCELED])
            ->get();

        foreach ($jobs as $job) {
            if ($this->retry($job, $actor)) {
                $results['success']++;
            } else {
                $results['skipped']++;
            }
        }

        $results['skipped'] = count($printJobIds) - $results['success'];

        return $results;
    }
}
