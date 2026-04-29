<?php

namespace App\Domain\Printing;

use App\Domain\Printing\Adapters\LanEscPosAdapter;
use App\Domain\Printing\Adapters\NullAdapter;
use App\Domain\Printing\Adapters\UsbAgentAdapter;
use App\Domain\Printing\Contracts\PrinterAdapter;
use App\Models\Printer;
use RuntimeException;

class PrinterAdapterRegistry
{
    /** @var array<int, PrinterAdapter> */
    private array $adapters;

    public function __construct(
        LanEscPosAdapter $lan,
        UsbAgentAdapter $usb,
        NullAdapter $null,
    ) {
        $this->adapters = [$lan, $usb, $null];
    }

    public function for(Printer $printer): PrinterAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($printer)) {
                return $adapter;
            }
        }

        throw new RuntimeException("No printer adapter supports printer {$printer->id}");
    }
}
