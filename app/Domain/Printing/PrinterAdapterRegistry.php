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
     * The lock key used for per-printer serialization. Public so
     * the stuck-job recovery command can check lock existence.
     */
    public static function lockKey(Printer $printer): string
    {
        return "printer:{$printer->id}";
    }
}
