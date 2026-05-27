<?php

namespace App\Console\Commands;

use App\Domain\Printing\PrinterAdapterRegistry;
use App\Models\Printer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Probes all active printers and updates their health status.
 *
 * Strategy (two-tier):
 *   1. Send a minimal ESC/POS probe via the configured adapter.
 *   2. If the adapter probe fails, attempt a bare TCP connect (ping) to
 *      distinguish between "printer offline" and "printer reachable but
 *      not responding to ESC/POS commands".
 *
 * Scheduling:
 *   - Scheduled via `routes/console.php` to run every minute.
 *   - A 90-second cache mutex prevents re-running within the window,
 *     yielding an effective ~90s interval.
 *   - Use `--force` to bypass the mutex (e.g., from the admin panel).
 *
 * Health status values set by this command:
 *   - OK:          adapter probe succeeded
 *   - REACHABLE:   probe failed but TCP connect succeeded (printer online, not responding)
 *   - UNREACHABLE: even TCP connect failed
 */
class HealthCheckPrinters extends Command
{
    protected $signature = 'serveo:health-check-printers {--force : Bypass the 90-second interval guard}';

    protected $description = 'Probe all active printers and update health status';

    /** Cache key for the 90-second interval mutex */
    private const MUTEX_KEY = 'health_check_printers_last_run';

    /** Minimum interval between scheduled runs in seconds */
    private const INTERVAL_SECONDS = 90;

    /** Minimal ESC/POS probe: init + status query (4 bytes) */
    private const PROBE_PAYLOAD = "\x1B\x40\x1D\x72\x01";

    /** TCP ping connect timeout in seconds */
    private const PING_TIMEOUT = 3.0;

    public function handle(PrinterAdapterRegistry $registry): int
    {
        // 90-second interval guard (bypassed with --force)
        if (! $this->option('force') && Cache::has(self::MUTEX_KEY)) {
            $this->info('Skipping health check — last run was less than '.self::INTERVAL_SECONDS.'s ago.');

            return Command::SUCCESS;
        }

        // Set mutex before running so concurrent invocations are gated
        Cache::put(self::MUTEX_KEY, now()->timestamp, self::INTERVAL_SECONDS);

        $printers = Printer::where('is_active', true)->get();

        if ($printers->isEmpty()) {
            $this->info('No active printers found.');

            return Command::SUCCESS;
        }

        $probed = 0;
        $ok = 0;
        $reachable = 0;
        $unreachable = 0;

        foreach ($printers as $printer) {
            $probed++;
            $result = $this->probePrinter($registry, $printer);

            match ($result) {
                'OK' => $ok++,
                'REACHABLE' => $reachable++,
                'UNREACHABLE' => $unreachable++,
            };
        }

        $this->info("Printer health check complete: {$probed} probed ({$ok} OK, {$reachable} reachable, {$unreachable} unreachable).");

        return Command::SUCCESS;
    }

    /**
     * Probe a single printer and update its health status.
     *
     * @return string The resulting health status key ('OK', 'REACHABLE', 'UNREACHABLE')
     */
    private function probePrinter(PrinterAdapterRegistry $registry, Printer $printer): string
    {
        // Tier 1: ESC/POS probe via adapter
        try {
            $adapter = $registry->for($printer);
            $result = $adapter->send($printer, self::PROBE_PAYLOAD);

            if ($result->success) {
                $printer->update([
                    'health_status' => 'OK',
                    'last_seen_at' => now(),
                    'last_error' => null,
                ]);

                return 'OK';
            }

            // Adapter probe failed — record the error for tier 2 fallback
            $adapterError = $result->message;
        } catch (\Throwable $e) {
            $adapterError = 'Adapter exception: '.$e->getMessage();
        }

        // Tier 2: TCP ping fallback
        if ($this->tcpPing($printer)) {
            $printer->update([
                'health_status' => 'REACHABLE',
                'last_seen_at' => now(),
                'last_error' => 'Probe failed but TCP reachable: '.$adapterError,
            ]);

            return 'REACHABLE';
        }

        $printer->update([
            'health_status' => 'UNREACHABLE',
            'last_error' => 'Unreachable: '.$adapterError,
        ]);

        return 'UNREACHABLE';
    }

    /**
     * Perform a bare TCP connect to verify network-level reachability.
     * No data is sent — we just verify the port accepts connections.
     */
    private function tcpPing(Printer $printer): bool
    {
        $host = $printer->address;
        $port = $printer->port ?: 9100;

        if (blank($host)) {
            return false;
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, self::PING_TIMEOUT);

        if ($socket === false) {
            return false;
        }

        @fclose($socket);

        return true;
    }
}
