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

            // ESC @  -> initialise printer (must come first — ESC @ resets codepage)
            $init = "\x1B\x40";
            // FS .   -> cancel Kanji/Chinese character mode
            //           (required on some printers before Western codepages take effect)
            $cancelKanji = "\x1C\x2E";
            // ESC t 16 -> select WPC1252 character code table (full Portuguese + €)
            $charsetInit = "\x1B\x74\x10";
            // GS V 1 -> partial cut (most ESC/POS cutters)
            // 5 newlines before cut give enough clearance from last printed line
            $cut = "\n\n\n\n\n\x1D\x56\x01";

            // Convert UTF-8 text payload to Windows-1252 (WPC1252) which includes
            // full Portuguese diacritics and the Euro sign (€ at 0x80).
            $encodedPayload = function_exists('iconv')
                ? iconv('UTF-8', 'Windows-1252//TRANSLIT', $payload)
                : (function_exists('mb_convert_encoding')
                    ? mb_convert_encoding($payload, 'Windows-1252', 'UTF-8')
                    : $payload);

            $data = $init.$cancelKanji.$charsetInit.$encodedPayload.$cut;

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
     * Send a raw cash-drawer kick pulse to the printer via TCP.
     *
     * Opens a raw socket, sends the ESC/POS drawer kick command
     * (ESC p 0 25 250 — pin 2, 25ms on, 250ms off), then closes.
     * No init, charset, or cut sequences are prepended — this is a
     * raw hardware pulse only.
     */
    public function openCashDrawer(Printer $printer): PrintResult
    {
        $host = $printer->address;
        $port = $printer->port ?: 9100;

        $errno = 0;
        $errstr = '';

        $socket = @fsockopen($host, $port, $errno, $errstr, self::CONNECT_TIMEOUT);
        if ($socket === false) {
            return PrintResult::fail("LAN printer {$host}:{$port} unreachable for drawer kick: {$errstr}");
        }

        try {
            // ESC p 0 25 250 — standard drawer kick pulse on pin 2
            // \x1B\x70 = ESC p command
            // \x00     = drawer pin 2 (0=pin 2)
            // \x19     = pulse on time (25 × 2ms = 50ms)
            // \xFA     = pulse off time (250 × 2ms = 500ms)
            $pulse = "\x1B\x70\x00\x19\xFA";

            $bytes = @fwrite($socket, $pulse);

            if ($bytes === false || $bytes === 0) {
                return PrintResult::fail("LAN printer {$host}:{$port} drawer kick: accepted no bytes");
            }

            return PrintResult::ok("Drawer kick sent to {$host}:{$port}");
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
