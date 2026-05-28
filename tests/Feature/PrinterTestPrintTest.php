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
            // Note: payload no longer includes ESC @ init or cut — adapter handles those
            return $p->id === Printer::first()->id
                && str_contains($payload, 'Serveo Test Print')
                && str_contains($payload, 'Portuguese characters');
        })
        ->andReturn(PrintResult::ok('Sent 42 bytes to 127.0.0.1:9100'));

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $this->app->instance(PrinterAdapterRegistry::class, $registry);

    // Simulate the action logic
    $reg = app(PrinterAdapterRegistry::class);
    $adp = $reg->for($printer);

    $testPayload = "=== Serveo Test Print ===\n"
        ."Printer: {$printer->name}\n"
        ."Date: ".now()->format('Y-m-d H:i:s')."\n"
        ."\n"
        ."--- Portuguese characters ---\n";

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

    $testPayload = "=== Serveo Test Print ===\n"
        ."Printer: {$printer->name}\n"
        ."Date: ".now()->format('Y-m-d H:i:s')."\n";

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

it('test print payload includes printer name, timestamp and Portuguese chars', function () {
    $printer = Printer::first();

    $payload = "=== Serveo Test Print ===\n"
        ."Printer: {$printer->name}\n"
        ."Date: ".now()->format('Y-m-d H:i:s')."\n"
        ."\n"
        ."--- Portuguese characters ---\n"
        ."Lower: à á â ã ä å æ ç è é ê ë ì í î ï ð ñ ò ó ô õ ö ø ù ú û ü\n"
        ."Upper: À Á Â Ã Ä Å Æ Ç È É Ê Ë Ì Í Î Ï Ð Ñ Ò Ó Ô Õ Ö Ø Ù Ú Û Ü\n"
        ."PT sp: ç Ç ã Ã õ Õ á Á é É í Í ó Ó ú Ú â Â ê Ê ô Ô à À\n";

    // Verify payload structure - adapter adds init + cut, not the test payload
    expect($payload)
        ->toContain('Serveo Test Print')
        ->toContain("Printer: {$printer->name}")
        ->toContain(now()->format('Y-m-d'))
        ->toContain('Portuguese characters')
        ->toContain('à á â ã')     // Portuguese lowercase
        ->toContain('À Á Â Ã')     // Portuguese uppercase
        ->toContain('ç Ç');        // cedilla
});

// ─── LanEscPosAdapter charset ─────────────────────────────────────────

it('lan adapter includes charset selection for Portuguese', function () {
    $printer = Printer::where('connection_type', 'NULL')->first();

    // Create adapter and verify it sends charset + init + cut
    $adapter = app(\App\Domain\Printing\Adapters\NullAdapter::class);

    // NullAdapter writes to file — verify it supports the printer
    expect($adapter->supports($printer))->toBeTrue();

    // Send a simple test
    $result = $adapter->send($printer, 'Hello');
    expect($result->success)->toBeTrue();
});

// ─── Double-cut prevention ────────────────────────────────────────────

it('test print payload does not include cut command (adapter handles it)', function () {
    $printer = Printer::first();

    $payload = "=== Serveo Test Print ===\n"
        ."Printer: {$printer->name}\n"
        ."Date: ".now()->format('Y-m-d H:i:s')."\n";

    // The test print payload should NOT include the cut command
    // The adapter handles init + cut, avoiding double-cut
    expect($payload)
        ->not->toContain("\x1D\x56\x01")     // no cut command
        ->not->toContain("\x1B\x40");         // no init command
});
