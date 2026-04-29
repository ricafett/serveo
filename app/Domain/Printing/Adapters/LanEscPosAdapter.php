<?php

namespace App\Domain\Printing\Adapters;

use App\Domain\Printing\Contracts\PrinterAdapter;
use App\Domain\Printing\PrintResult;
use App\Models\Printer;

/**
 * Direct LAN/Ethernet ESC/POS printing using a raw TCP socket on port 9100.
 *
 * Renders a plain UTF-8 payload terminated by a feed + cut sequence.
 * Falls back gracefully if the printer is unreachable.
 */
class LanEscPosAdapter implements PrinterAdapter
{
    public function supports(Printer $printer): bool
    {
        return $printer->connection_type === Printer::CONN_LAN
            && filled($printer->address);
    }

    public function send(Printer $printer, string $payload): PrintResult
    {
        $host = $printer->address;
        $port = $printer->port ?: 9100;

        $errno  = 0;
        $errstr = '';

        // 4-second connect timeout so the worker doesn't stall service.
        $socket = @fsockopen($host, $port, $errno, $errstr, 4.0);
        if ($socket === false) {
            return PrintResult::fail("LAN printer {$host}:{$port} unreachable: {$errstr}");
        }

        stream_set_timeout($socket, 5);

        // ESC @  -> initialise printer
        $init  = "\x1B\x40";
        // GS V 1 -> partial cut (most ESC/POS cutters)
        $cut   = "\n\n\n\x1D\x56\x01";

        $bytes = @fwrite($socket, $init.$payload.$cut);
        @fclose($socket);

        if ($bytes === false || $bytes === 0) {
            return PrintResult::fail("LAN printer {$host}:{$port} accepted no bytes");
        }

        return PrintResult::ok("Sent {$bytes} bytes to {$host}:{$port}");
    }
}
