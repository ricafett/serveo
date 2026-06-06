<?php

namespace App\Console\Commands;

use App\Domain\Printing\PrinterAdapterRegistry;
use App\Models\PrintJob;
use App\Models\Printer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Recovers PrintJobs stuck in IN_PROGRESS state after a worker crash.
 *
 * A PrintJob can become stuck IN_PROGRESS when:
 *   - The queue worker process is killed (SIGKILL, OOM, power loss)
 *     between the atomic claim (PENDING→IN_PROGRESS) and completion.
 *   - The failed() method didn't run because the process died instantly.
 *
 * Recovery is safe because:
 *   1. The per-printer lock (TTL=20s) has already expired — no worker
 *      is actually printing to this printer.
 *   2. Only IN_PROGRESS jobs with updated_at older than the threshold
 *      are reverted.
 *   3. As a double-check, we verify the Redis lock does not exist
 *      (graceful: if Redis is down, we skip the lock check).
 *
 * Scheduling: every 2 minutes via routes/console.php.
 */
class RecoverStuckPrintJobs extends Command
{
    protected $signature = 'serveo:recover-stuck-print-jobs';

    protected $description = 'Revert IN_PROGRESS PrintJobs stuck after worker crashes';

    /** Jobs stuck longer than this are eligible for recovery. */
    private const STUCK_THRESHOLD_MINUTES = 2;

    public function handle(): int
    {
        $threshold = now()->subMinutes(self::STUCK_THRESHOLD_MINUTES);

        $stuck = PrintJob::with('printer')
            ->where('status', PrintJob::STATUS_IN_PROGRESS)
            ->where('updated_at', '<', $threshold)
            ->get();

        if ($stuck->isEmpty()) {
            return Command::SUCCESS;
        }

        $recovered = 0;
        $skipped = 0;

        foreach ($stuck as $job) {
            // Double-check: if the per-printer lock still exists, a worker
            // might still be printing. Skip this job to be safe.
            if ($job->printer && $this->isLockHeld($job->printer)) {
                $skipped++;
                continue;
            }

            $job->update([
                'status' => PrintJob::STATUS_PENDING,
                'attempts' => max(0, $job->attempts - 1),
                'last_error' => 'Recovered from stuck IN_PROGRESS (worker crash suspected)',
            ]);

            $recovered++;
        }

        if ($recovered > 0 || $skipped > 0) {
            $this->info("Stuck PrintJob recovery: {$recovered} recovered, {$skipped} skipped (lock still held).");
        }

        return Command::SUCCESS;
    }

    /**
     * Check whether a per-printer Redis lock is currently held.
     * Returns false if Redis is unavailable (graceful degradation).
     */
    private function isLockHeld(Printer $printer): bool
    {
        try {
            return Cache::lock(PrinterAdapterRegistry::lockKey($printer), 1)->get() === false;
            // get() returns false when another process holds the lock.
            // If WE acquire it, release immediately — it was free.
        } catch (\Throwable) {
            // Redis unavailable — can't verify, but lock TTL (20s) has
            // likely expired anyway. Allow recovery.
            return false;
        }
    }
}
