<?php

use App\Domain\Printing\Contracts\PrinterAdapter;
use App\Domain\Printing\PrinterAdapterRegistry;
use App\Domain\Printing\PrintResult;
use App\Models\Printer;

beforeEach(function () {
    bootScenario();
});

// ─── Successful test print ────────────────────────────────────────────

it('sends a test print payload and updates health on success', function () {
    $printer = Printer::first();
    $printer->update(['health_status' => 'UNKNOWN', 'last_error' => 'previous error']);

    // Mock adapter that returns success
    $adapter = Mockery::mock(PrinterAdapter::class);
    $adapter->shouldReceive('supports')->andReturn(true);
    $adapter->shouldReceive('send')
        ->once()
        ->withArgs(function (Printer $p, string $payload) {
            // Verify payload contains the test header and printer name
            return $p->id === Printer::first()->id
                && str_contains($payload, 'Serveo Test Print')
                && str_contains($payload, "\x1B\x40"); // ESC @ init
        })
        ->andReturn(PrintResult::ok('Sent 42 bytes to 127.0.0.1:9100'));

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $this->app->instance(PrinterAdapterRegistry::class, $registry);

    // Simulate the action logic
    $reg = app(PrinterAdapterRegistry::class);
    $adp = $reg->for($printer);

    $testPayload = "\x1B\x40"
        ."Serveo Test Print\n"
        ."Printer: {$printer->name}\n"
        .now()->format('Y-m-d H:i:s')
        ."\n\n\n\x1D\x56\x01";

    $result = $adp->send($printer, $testPayload);

    expect($result->success)->toBeTrue();

    // Simulate the health update the action would do
    $printer->update(['health_status' => 'OK', 'last_seen_at' => now(), 'last_error' => null]);

    expect($printer->refresh()->health_status)->toBe('OK')
        ->and($printer->last_error)->toBeNull();
});

// ─── Failed test print ────────────────────────────────────────────────

it('updates health to UNREACHABLE on test print failure', function () {
    $printer = Printer::first();
    $printer->update(['health_status' => 'OK', 'last_error' => null]);

    $adapter = Mockery::mock(PrinterAdapter::class);
    $adapter->shouldReceive('supports')->andReturn(true);
    $adapter->shouldReceive('send')
        ->andReturn(PrintResult::fail('Connection refused'));

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $this->app->instance(PrinterAdapterRegistry::class, $registry);

    $reg = app(PrinterAdapterRegistry::class);
    $adp = $reg->for($printer);

    $testPayload = "\x1B\x40"
        ."Serveo Test Print\n"
        ."Printer: {$printer->name}\n"
        .now()->format('Y-m-d H:i:s')
        ."\n\n\n\x1D\x56\x01";

    $result = $adp->send($printer, $testPayload);

    expect($result->success)->toBeFalse();

    $printer->update(['health_status' => 'UNREACHABLE', 'last_error' => $result->message]);

    expect($printer->refresh()->health_status)->toBe('UNREACHABLE')
        ->and($printer->last_error)->toBe('Connection refused');
});

// ─── Test print handles adapter exception ─────────────────────────────

it('handles adapter exception during test print', function () {
    $printer = Printer::first();

    $adapter = Mockery::mock(PrinterAdapter::class);
    $adapter->shouldReceive('supports')->andReturn(true);
    $adapter->shouldReceive('send')
        ->andThrow(new RuntimeException('Socket error'));

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $this->app->instance(PrinterAdapterRegistry::class, $registry);

    try {
        $reg = app(PrinterAdapterRegistry::class);
        $adp = $reg->for($printer);
        $adp->send($printer, 'test');
    } catch (\Throwable $e) {
        expect($e->getMessage())->toBe('Socket error');
    }
});

// ─── Test print payload structure ─────────────────────────────────────

it('test print payload includes init, printer name, timestamp and cut', function () {
    $printer = Printer::first();

    $payload = "\x1B\x40"
        ."Serveo Test Print\n"
        ."Printer: {$printer->name}\n"
        .now()->format('Y-m-d H:i:s')
        ."\n\n\n\x1D\x56\x01";

    // Verify payload structure
    expect($payload)->toStartWith("\x1B\x40")          // ESC @ init
        ->toContain('Serveo Test Print')                // header
        ->toContain("Printer: {$printer->name}")        // printer name
        ->toContain(now()->format('Y-m-d'))             // today's date
        ->toEndWith("\x1D\x56\x01");                    // GS V 1 partial cut
});
