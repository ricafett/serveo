<?php

namespace App\Jobs;

use App\Domain\Audit\Audit;
use App\Domain\Printing\PrinterAdapterRegistry;
use App\Models\Printer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sends a cash-drawer kick pulse to a single printer.
 *
 * Concurrency: uses the same per-printer Redis lock as DispatchPrintJob
 * via PrinterAdapterRegistry. On contention (printer busy printing),
 * self-re-dispatches with a short backoff.
 *
 * Self-managed retries (Laravel $tries = 1): the worker won't retry
 * on exception; the job handles failures internally via self-dispatch.
 */
class OpenCashDrawerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $printerId,
        public ?int $actorId,
        public int $attempt = 0,
    ) {}

    public function handle(PrinterAdapterRegistry $registry): void
    {
        $printer = Printer::find($this->printerId);

        if (! $printer) {
            Audit::record(
                'CASH_DRAWER_OPEN_FAILED',
                'Cash drawer open failed: printer not found',
                ['printer_id' => $this->printerId],
                ['actor_user_id' => $this->actorId],
            );

            return;
        }

        $result = $registry->openCashDrawer($printer);

        // ── Lock contention → re-dispatch with backoff ──
        if ($result->contended && $this->attempt < 2) {
            static::dispatch($this->printerId, $this->actorId, $this->attempt + 1)
                ->onQueue('prints')
                ->delay(now()->addSeconds(3));

            return;
        }

        // ── Success ──
        if ($result->success) {
            Audit::record(
                'CASH_DRAWER_OPENED',
                "Cash drawer opened via printer #{$printer->id} ({$printer->name})",
                ['printer_id' => $printer->id],
                ['actor_user_id' => $this->actorId],
            );

            return;
        }

        // ── Transport failure ──
        Audit::record(
            'CASH_DRAWER_OPEN_FAILED',
            "Cash drawer open failed: {$result->message}",
            ['printer_id' => $printer->id, 'error' => $result->message],
            ['actor_user_id' => $this->actorId],
        );

        // Retry once on transport failure
        if ($this->attempt < 2) {
            static::dispatch($this->printerId, $this->actorId, $this->attempt + 1)
                ->onQueue('prints')
                ->delay(now()->addSeconds(3));
        }
    }

    /**
     * Called by Laravel when the job fails with an unhandled exception
     * (e.g. worker killed by --timeout, fatal PHP error, SIGKILL).
     */
    public function failed(Throwable $e): void
    {
        Audit::record(
            'CASH_DRAWER_OPEN_FAILED',
            'Cash drawer open failed: worker failure — '.($e->getMessage() ?: 'process terminated'),
            ['printer_id' => $this->printerId, 'error' => $e->getMessage()],
            ['actor_user_id' => $this->actorId],
        );
    }
}
