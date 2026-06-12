<?php

namespace App\Domain\Printing;

use App\Domain\Printing\Adapters\LanEscPosAdapter;
use App\Domain\Printing\Adapters\NullAdapter;
use App\Domain\Printing\Adapters\UsbAgentAdapter;
use App\Domain\Printing\Contracts\PrinterAdapter;
use App\Models\Printer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PrinterAdapterRegistry
{
    /**
     * How long the per-printer lock lives before auto-expiring.
     * Must match the queue worker --timeout for prints so a killed
     * worker releases the lock at the same time Laravel kills it.
     */
    private const LOCK_TTL = 20;

    /** @var array<int, PrinterAdapter> */
    private array $adapters;

    public function __construct(
        LanEscPosAdapter $lan,
        UsbAgentAdapter $usb,
        NullAdapter $null,
    ) {
        $this->adapters = [$lan, $usb, $null];
    }

    /**
     * Resolve the raw adapter for a printer. Callers that need
     * serialized access should use send() / probe() instead.
     */
    public function for(Printer $printer): PrinterAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($printer)) {
                return $adapter;
            }
        }

        throw new RuntimeException("No printer adapter supports printer {$printer->id}");
    }

    /**
     * Send a payload to a printer, serializing access via a non-blocking
     * per-printer lock. If the lock is held (another worker is printing),
     * returns a contended result immediately so the caller can re-dispatch.
     *
     * Different printers run concurrently; same-printer jobs serialize
     * via delayed re-dispatch in the caller.
     */
    public function send(Printer $printer, string $payload): PrintResult
    {
        $lockKey = self::lockKey($printer);

        try {
            $lock = Cache::lock($lockKey, self::LOCK_TTL);

            if (! $lock->get()) {
                return PrintResult::contended("Printer {$printer->id} busy — lock held by another worker");
            }

            try {
                return $this->for($printer)->send($printer, $payload);
            } finally {
                $lock->release();
            }
        } catch (Throwable $e) {
            // Redis unavailable — proceed without lock (degraded, not dead)
            Log::warning('Printer lock unavailable, printing unlocked', [
                'printer_id' => $printer->id,
                'error' => $e->getMessage(),
            ]);

            return $this->for($printer)->send($printer, $payload);
        }
    }

    /**
     * Probe a printer using the adapter's lightweight status query.
     * Non-blocking: if the lock is held, fail immediately so the health
     * check falls through to TCP ping → REACHABLE.
     */
    public function probe(Printer $printer): PrintResult
    {
        $lockKey = self::lockKey($printer);

        try {
            $lock = Cache::lock($lockKey, self::LOCK_TTL);

            if (! $lock->get()) {
                return PrintResult::fail("Printer {$printer->id} busy — skipping probe");
            }

            try {
                return $this->for($printer)->probe($printer);
            } finally {
                $lock->release();
            }
        } catch (Throwable $e) {
            Log::warning('Printer lock unavailable during probe, probing unlocked', [
                'printer_id' => $printer->id,
                'error' => $e->getMessage(),
            ]);

            return $this->for($printer)->probe($printer);
        }
    }

    /**
     * Send a payload multiple times to a printer, holding the per-printer
     * lock for the entire batch to prevent interleaving with other jobs.
     * Returns failure on the first copy that fails, success if all succeed.
     */
    public function sendBatch(Printer $printer, string $payload, int $times): PrintResult
    {
        if ($times < 1) {
            return PrintResult::ok('No copies requested');
        }

        $lockKey = self::lockKey($printer);

        try {
            $lock = Cache::lock($lockKey, self::LOCK_TTL);

            if (! $lock->get()) {
                return PrintResult::contended("Printer {$printer->id} busy — lock held by another worker");
            }

            try {
                $adapter = $this->for($printer);

                for ($i = 0; $i < $times; $i++) {
                    $result = $adapter->send($printer, $payload);

                    if (! $result->success) {
                        return PrintResult::fail("Batch copy {$i} of {$times} failed: ".$result->message);
                    }
                }

                return PrintResult::ok("Sent {$times} copies to printer {$printer->id}");
            } finally {
                $lock->release();
            }
        } catch (Throwable $e) {
            Log::warning('Printer lock unavailable during batch, printing unlocked', [
                'printer_id' => $printer->id,
                'error' => $e->getMessage(),
            ]);

            $adapter = $this->for($printer);

            for ($i = 0; $i < $times; $i++) {
                $result = $adapter->send($printer, $payload);

                if (! $result->success) {
                    return PrintResult::fail("Batch copy {$i} of {$times} failed: ".$result->message);
                }
            }

            return PrintResult::ok("Sent {$times} copies to printer {$printer->id}");
        }
    }

    /**
     * Send a cash-drawer kick pulse to a printer, serializing access via
     * the same per-printer lock used by send(). If the lock is held,
     * returns a contended result immediately so the caller can re-dispatch.
     */
    public function openCashDrawer(Printer $printer): PrintResult
    {
        $lockKey = self::lockKey($printer);

        try {
            $lock = Cache::lock($lockKey, self::LOCK_TTL);

            if (! $lock->get()) {
                return PrintResult::contended("Printer {$printer->id} busy — lock held by another worker");
            }

            try {
                return $this->for($printer)->openCashDrawer($printer);
            } finally {
                $lock->release();
            }
        } catch (Throwable $e) {
            Log::warning('Printer lock unavailable during drawer kick, proceeding unlocked', [
                'printer_id' => $printer->id,
                'error' => $e->getMessage(),
            ]);

            return $this->for($printer)->openCashDrawer($printer);
        }
    }

    /**
     * The lock key used for per-printer serialization. Public so
     * the stuck-job recovery command can check lock existence.
     */
    public static function lockKey(Printer $printer): string
    {
        return "printer:{$printer->id}";
    }
}
