<?php

namespace Tests\Traits;

use App\Domain\Printing\PrintResult;
use App\Models\Printer;

/**
 * Default probe() implementation for anonymous PrinterAdapter stubs.
 *
 * Delegates to send() with a minimal payload so that test adapters
 * don't need to distinguish probe from print.
 */
trait DelegatesProbeToSend
{
    public function probe(Printer $printer): PrintResult
    {
        return $this->send($printer, '');
    }
}
