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
 *
 * Socket safety:
 *   - stream_select() enforces a 5-second write timeout (PHP's
 *     stream_set_timeout only affects reads, not writes).
 *   - try/finally guarantees fclose() even if fwrite() throws.
 */
class LanEscPosAdapter implements PrinterAdapter
{
    private const CONNECT_TIMEOUT = 4.0;
    private const WRITE_TIMEOUT = 5;
    private const READ_TIMEOUT = 5;

    public function supports(Printer $printer): bool
    {
        return $printer->connection_type === Printer::CONN_LAN
            && filled($printer->address);
    }

    public function send(Printer $printer, string $payload): PrintResult
    {
        $host = $printer->address;
        $port = $printer->port ?: 9100;

        $errno = 0;
        $errstr = '';

        $socket = @fsockopen($host, $port, $errno, $errstr, self::CONNECT_TIMEOUT);
        if ($socket === false) {
            return PrintResult::fail("LAN printer {$host}:{$port} unreachable: {$errstr}");
        }

        try {
            stream_set_timeout($socket, self::READ_TIMEOUT);

            // ESC @  -> initialise printer
            $init = "\x1B\x40";
            // GS V 1 -> partial cut (most ESC/POS cutters)
            $cut = "\n\n\n\x1D\x56\x01";

            $data = $init.$payload.$cut;

            // Enforce write timeout via stream_select().
            // PHP's stream_set_timeout() only affects fread(), not fwrite().
            $write = [$socket];
            $except = [$socket];
            $read = null;
            $selectResult = @stream_select($read, $write, $except, self::WRITE_TIMEOUT);

            if ($selectResult === false) {
                return PrintResult::fail("LAN printer {$host}:{$port} stream_select error");
            }

            if ($selectResult === 0) {
                return PrintResult::fail("LAN printer {$host}:{$port} write timeout after ".self::WRITE_TIMEOUT.'s');
            }

            if (in_array($socket, $except, true)) {
                return PrintResult::fail("LAN printer {$host}:{$port} connection exception");
            }

            $bytes = @fwrite($socket, $data);

            if ($bytes === false || $bytes === 0) {
                return PrintResult::fail("LAN printer {$host}:{$port} accepted no bytes");
            }

            return PrintResult::ok("Sent {$bytes} bytes to {$host}:{$port}");
        } finally {
            @fclose($socket);
        }
    }

    /**
     * Lightweight connectivity probe — no paper feed, no cut.
     *
     * Opens a TCP socket, sends ESC @ (init) + GS r 1 (status query),
     * reads the response byte, then closes. Suitable for scheduled health
     * checks that must not waste paper or interrupt in-progress tickets.
     */
    public function probe(Printer $printer): PrintResult
    {
        $host = $printer->address;
        $port = $printer->port ?: 9100;

        $socket = @fsockopen($host, $port, $errno, $errstr, self::CONNECT_TIMEOUT);
        if ($socket === false) {
            return PrintResult::fail("LAN printer {$host}:{$port} unreachable: {$errstr}");
        }

        try {
            stream_set_timeout($socket, 2);

            // ESC @ init + GS r 1 status query — no feed, no cut
            $probePayload = "\x1B\x40\x1D\x72\x01";

            $bytes = @fwrite($socket, $probePayload);

            if ($bytes === false || $bytes === 0) {
                return PrintResult::fail("LAN printer {$host}:{$port} probe write failed");
            }

            // Try to read the status response byte (printer may or may not respond)
            $response = @fread($socket, 1);

            return PrintResult::ok("LAN printer {$host}:{$port} probed OK");
        } finally {
            @fclose($socket);
        }
    }
}
