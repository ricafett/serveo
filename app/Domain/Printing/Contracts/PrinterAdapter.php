<?php

namespace App\Domain\Printing\Contracts;

use App\Domain\Printing\PrintResult;
use App\Models\Printer;

/**
 * PrinterAdapter sends a rendered payload to a single printer.
 *
 * Implementations are owned by the backend. Browser printing is never the
 * source of truth: only adapters defined here are allowed to fulfill print jobs.
 */
interface PrinterAdapter
{
    /**
     * Whether this adapter can handle the given printer (matches connection_type).
     */
    public function supports(Printer $printer): bool;

    /**
     * Attempt to deliver the payload (already-rendered text/escpos string)
     * to the printer. MUST NOT throw on transport errors; return PrintResult::fail.
     */
    public function send(Printer $printer, string $payload): PrintResult;

    /**
     * Lightweight connectivity check. MUST NOT produce any mechanical
     * side effects — no paper feed, no cut. Used by the health-check
     * scheduler and the admin test-print diagnostic.
     *
     * Default implementation falls back to send() for adapters that
     * don't need to distinguish probe from print.
     */
    public function probe(Printer $printer): PrintResult;
}
