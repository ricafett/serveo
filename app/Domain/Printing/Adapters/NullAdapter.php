<?php

namespace App\Domain\Printing\Adapters;

use App\Domain\Printing\Contracts\PrinterAdapter;
use App\Domain\Printing\PrintResult;
use App\Models\Printer;
use Illuminate\Support\Facades\Storage;

/**
 * Always-succeed adapter used for local development, demos, and tests.
 *
 * Writes payloads to storage/app/print-jobs/{timestamp}_{printer}.txt so
 * operators and tests can inspect what would have printed.
 */
class NullAdapter implements PrinterAdapter
{
    public function supports(Printer $printer): bool
    {
        return $printer->connection_type === Printer::CONN_NULL
            || ! in_array($printer->connection_type, [Printer::CONN_LAN, Printer::CONN_USB_AGENT], true);
    }

    public function send(Printer $printer, string $payload): PrintResult
    {
        $path = sprintf(
            'print-jobs/%s_printer-%d.txt',
            now()->format('Ymd_His_v'),
            $printer->id,
        );
        Storage::disk('local')->put($path, $payload);

        return PrintResult::ok("Wrote payload to storage: {$path}");
    }
}
