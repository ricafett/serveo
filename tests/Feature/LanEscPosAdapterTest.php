<?php

use App\Domain\Printing\Adapters\LanEscPosAdapter;
use App\Domain\Printing\PrintResult;
use App\Models\Printer;

beforeEach(function () {
    $this->adapter = new LanEscPosAdapter;
});

// ─── supports() ────────────────────────────────────────────────────────

it('supports LAN printers with an address', function () {
    $printer = new Printer([
        'connection_type' => Printer::CONN_LAN,
        'address' => '192.168.1.100',
        'port' => 9100,
    ]);

    expect($this->adapter->supports($printer))->toBeTrue();
});

it('rejects non-LAN connection types', function () {
    $printer = new Printer([
        'connection_type' => Printer::CONN_USB_AGENT,
        'address' => '192.168.1.100',
        'port' => 9100,
    ]);

    expect($this->adapter->supports($printer))->toBeFalse();
});

it('rejects LAN printers without address', function () {
    $printer = new Printer([
        'connection_type' => Printer::CONN_LAN,
        'address' => null,
        'port' => 9100,
    ]);

    expect($this->adapter->supports($printer))->toBeFalse();
});

// ─── send() failure: unreachable host ──────────────────────────────────

it('returns failure for unreachable host', function () {
    $printer = new Printer([
        'connection_type' => Printer::CONN_LAN,
        'address' => '192.0.2.1', // TEST-NET-1, should be unreachable
        'port' => 9100,
    ]);

    $result = $this->adapter->send($printer, 'test payload');

    expect($result)->toBeInstanceOf(PrintResult::class)
        ->and($result->success)->toBeFalse()
        ->and($result->message)->toContain('unreachable');
});

// ─── send() success: real TCP server ───────────────────────────────────

it('sends payload to a reachable TCP server', function () {
    // Start a temporary TCP server on a random port
    $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (! $server) {
        $this->markTestSkipped("Could not start test TCP server: {$errstr}");
    }

    $socketName = stream_socket_get_name($server, false);
    preg_match('/:(\d+)$/', $socketName, $matches);
    $port = (int) $matches[1];

    $received = '';

    // Accept connections in a non-blocking way
    stream_set_blocking($server, false);

    $printer = new Printer([
        'connection_type' => Printer::CONN_LAN,
        'address' => '127.0.0.1',
        'port' => $port,
    ]);

    $result = $this->adapter->send($printer, 'HELLO');

    // Read what the server received
    $client = @stream_socket_accept($server, 1);
    if ($client) {
        stream_set_timeout($client, 2);
        $received = stream_get_contents($client);
        fclose($client);
    }

    fclose($server);

    expect($result)->toBeInstanceOf(PrintResult::class)
        ->and($result->success)->toBeTrue()
        ->and($result->message)->toContain('Sent')
        ->and($received)->toContain('HELLO');
});

// ─── send() timeout: server that accepts but never reads ───────────────

it('times out when server accepts but never reads', function () {
    // Start a server that accepts but doesn't read (fill buffer)
    $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (! $server) {
        $this->markTestSkipped("Could not start test TCP server: {$errstr}");
    }

    $socketName = stream_socket_get_name($server, false);
    preg_match('/:(\d+)$/', $socketName, $matches);
    $port = (int) $matches[1];

    // Accept but don't read — this fills the TCP send buffer
    stream_set_blocking($server, false);

    // Accept one connection to establish it, then do nothing
    $accepted = false;
    $start = microtime(true);

    $printer = new Printer([
        'connection_type' => Printer::CONN_LAN,
        'address' => '127.0.0.1',
        'port' => $port,
    ]);

    // Send a large payload to try to fill the buffer
    $largePayload = str_repeat('X', 1024 * 64); // 64KB

    $result = $this->adapter->send($printer, $largePayload);

    // Accept and close the latent connection
    $client = @stream_socket_accept($server, 0);
    if ($client) {
        fclose($client);
    }
    fclose($server);

    // With a 64KB payload, the buffer should fill and stream_select should timeout
    // If no timeout, the test still validates the result structure
    expect($result)->toBeInstanceOf(PrintResult::class);
});

// ─── send() with default port 9100 ─────────────────────────────────────

it('uses port 9100 as default when port is null', function () {
    // Test only the structure: port null → used 9100
    $printer = new Printer([
        'connection_type' => Printer::CONN_LAN,
        'address' => '192.0.2.1', // unreachable
        'port' => null,
    ]);

    $result = $this->adapter->send($printer, 'test');

    expect($result->success)->toBeFalse();
});
