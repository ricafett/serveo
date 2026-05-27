<?php

use App\Console\Commands\HealthCheckPrinters;
use App\Domain\Printing\Contracts\PrinterAdapter;
use App\Domain\Printing\PrinterAdapterRegistry;
use App\Domain\Printing\PrintResult;
use App\Models\Printer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Clean up any printers from bootScenario so we control the test data
    Printer::query()->update(['is_active' => false]);
    // Clear the health check mutex so tests don't interfere
    Cache::forget('health_check_printers_last_run');
});

// ─── Command exists and is registered ──────────────────────────────────

it('command is registered in the application', function () {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('serveo:health-check-printers');
});

// ─── OK: adapter probe succeeds ────────────────────────────────────────

it('sets health_status to OK when adapter probe succeeds', function () {
    $printer = Printer::create([
        'name' => 'Test Printer',
        'printer_type' => Printer::TYPE_KITCHEN,
        'connection_type' => Printer::CONN_LAN,
        'address' => '192.168.1.1',
        'port' => 9100,
        'is_active' => true,
        'health_status' => 'UNKNOWN',
    ]);

    $adapter = Mockery::mock(PrinterAdapter::class);
    $adapter->shouldReceive('supports')->andReturn(true);
    $adapter->shouldReceive('send')->andReturn(PrintResult::ok('Sent 4 bytes'));

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $this->app->instance(PrinterAdapterRegistry::class, $registry);

    Artisan::call('serveo:health-check-printers', ['--force' => true]);

    $printer->refresh();
    expect($printer->health_status)->toBe('OK')
        ->and($printer->last_error)->toBeNull();
});

// ─── REACHABLE: adapter fails but TCP ping succeeds ─────────────────────

it('sets health_status to REACHABLE when probe fails but TCP ping succeeds', function () {
    // Start a temporary TCP server for the ping to succeed
    $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (! $server) {
        $this->markTestSkipped("Could not start test TCP server: {$errstr}");
    }

    $socketName = stream_socket_get_name($server, false);
    preg_match('/:(\d+)$/', $socketName, $matches);
    $port = (int) $matches[1];

    $printer = Printer::create([
        'name' => 'Test Printer Reachable',
        'printer_type' => Printer::TYPE_KITCHEN,
        'connection_type' => Printer::CONN_LAN,
        'address' => '127.0.0.1',
        'port' => $port,
        'is_active' => true,
        'health_status' => 'UNKNOWN',
    ]);

    // Adapter probe fails
    $adapter = Mockery::mock(PrinterAdapter::class);
    $adapter->shouldReceive('supports')->andReturn(true);
    $adapter->shouldReceive('send')->andReturn(PrintResult::fail('Printer busy'));

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $this->app->instance(PrinterAdapterRegistry::class, $registry);

    Artisan::call('serveo:health-check-printers', ['--force' => true]);

    $printer->refresh();
    expect($printer->health_status)->toBe('REACHABLE')
        ->and($printer->last_error)->toContain('Probe failed but TCP reachable');

    // Clean up server
    $client = @stream_socket_accept($server, 0);
    if ($client) {
        fclose($client);
    }
    fclose($server);
});

// ─── UNREACHABLE: both probe and TCP ping fail ─────────────────────────

it('sets health_status to UNREACHABLE when both probe and ping fail', function () {
    $printer = Printer::create([
        'name' => 'Dead Printer',
        'printer_type' => Printer::TYPE_KITCHEN,
        'connection_type' => Printer::CONN_LAN,
        'address' => '192.0.2.1', // TEST-NET-1, unreachable
        'port' => 9100,
        'is_active' => true,
        'health_status' => 'OK',
    ]);

    $adapter = Mockery::mock(PrinterAdapter::class);
    $adapter->shouldReceive('supports')->andReturn(true);
    $adapter->shouldReceive('send')->andReturn(PrintResult::fail('Connection refused'));

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $this->app->instance(PrinterAdapterRegistry::class, $registry);

    Artisan::call('serveo:health-check-printers', ['--force' => true]);

    $printer->refresh();
    expect($printer->health_status)->toBe('UNREACHABLE')
        ->and($printer->last_error)->toContain('Unreachable');
});

// ─── Skips inactive printers ───────────────────────────────────────────

it('skips inactive printers', function () {
    $activePrinter = Printer::create([
        'name' => 'Active Printer',
        'printer_type' => Printer::TYPE_KITCHEN,
        'connection_type' => Printer::CONN_LAN,
        'address' => '192.168.1.1',
        'port' => 9100,
        'is_active' => true,
        'health_status' => 'UNKNOWN',
    ]);

    $inactivePrinter = Printer::create([
        'name' => 'Inactive Printer',
        'printer_type' => Printer::TYPE_BAR,
        'connection_type' => Printer::CONN_LAN,
        'address' => '192.168.1.2',
        'port' => 9100,
        'is_active' => false,
        'health_status' => 'UNKNOWN',
    ]);

    $adapter = Mockery::mock(PrinterAdapter::class);
    $adapter->shouldReceive('supports')->andReturn(true);
    // Should only be called once (for the active printer)
    $adapter->shouldReceive('send')->once()->andReturn(PrintResult::ok('OK'));

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $this->app->instance(PrinterAdapterRegistry::class, $registry);

    Artisan::call('serveo:health-check-printers', ['--force' => true]);

    // Inactive printer should remain UNKNOWN
    $inactivePrinter->refresh();
    expect($inactivePrinter->health_status)->toBe('UNKNOWN');

    // Active printer should be updated
    $activePrinter->refresh();
    expect($activePrinter->health_status)->toBe('OK');
});

// ─── Handles adapter exception ─────────────────────────────────────────

it('handles adapter exception gracefully and falls back to ping', function () {
    $printer = Printer::create([
        'name' => 'Buggy Printer',
        'printer_type' => Printer::TYPE_KITCHEN,
        'connection_type' => Printer::CONN_LAN,
        'address' => '192.0.2.1', // unreachable for ping too
        'port' => 9100,
        'is_active' => true,
        'health_status' => 'UNKNOWN',
    ]);

    $adapter = Mockery::mock(PrinterAdapter::class);
    $adapter->shouldReceive('supports')->andReturn(true);
    $adapter->shouldReceive('send')->andThrow(new RuntimeException('Adapter exploded'));

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $this->app->instance(PrinterAdapterRegistry::class, $registry);

    // Should not throw
    Artisan::call('serveo:health-check-printers', ['--force' => true]);

    $printer->refresh();
    expect($printer->health_status)->toBe('UNREACHABLE')
        ->and($printer->last_error)->toContain('Adapter exception');
});

// ─── Output message ────────────────────────────────────────────────────

it('outputs summary after probing', function () {
    $printer = Printer::create([
        'name' => 'Test Printer',
        'printer_type' => Printer::TYPE_KITCHEN,
        'connection_type' => Printer::CONN_LAN,
        'address' => '192.0.2.1',
        'port' => 9100,
        'is_active' => true,
        'health_status' => 'UNKNOWN',
    ]);

    $adapter = Mockery::mock(PrinterAdapter::class);
    $adapter->shouldReceive('supports')->andReturn(true);
    $adapter->shouldReceive('send')->andReturn(PrintResult::fail('Down'));

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $this->app->instance(PrinterAdapterRegistry::class, $registry);

    $exitCode = Artisan::call('serveo:health-check-printers', ['--force' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Printer health check complete')
        ->and($output)->toContain('1 probed');
});

// ─── No active printers ────────────────────────────────────────────────

it('handles no active printers gracefully', function () {
    $exitCode = Artisan::call('serveo:health-check-printers', ['--force' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('No active printers');
});

// ─── 90-second cache mutex ─────────────────────────────────────────────

it('skips run when mutex cache key exists', function () {
    // Pre-set the mutex
    Cache::put('health_check_printers_last_run', now()->timestamp, 90);

    $printer = Printer::create([
        'name' => 'Test Printer',
        'printer_type' => Printer::TYPE_KITCHEN,
        'connection_type' => Printer::CONN_LAN,
        'address' => '192.168.1.1',
        'port' => 9100,
        'is_active' => true,
        'health_status' => 'UNKNOWN',
    ]);

    $adapter = Mockery::mock(PrinterAdapter::class);
    $adapter->shouldReceive('supports')->andReturn(true);
    // send() should NEVER be called because the mutex blocks execution
    $adapter->shouldReceive('send')->never();

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    // for() should NEVER be called
    $registry->shouldReceive('for')->never();

    $this->app->instance(PrinterAdapterRegistry::class, $registry);

    $exitCode = Artisan::call('serveo:health-check-printers');
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('Skipping health check');

    // Printer status should remain unchanged
    $printer->refresh();
    expect($printer->health_status)->toBe('UNKNOWN');
});

it('--force bypasses the mutex even when cache key exists', function () {
    // Pre-set the mutex
    Cache::put('health_check_printers_last_run', now()->timestamp, 90);

    $printer = Printer::create([
        'name' => 'Test Printer',
        'printer_type' => Printer::TYPE_KITCHEN,
        'connection_type' => Printer::CONN_LAN,
        'address' => '192.168.1.1',
        'port' => 9100,
        'is_active' => true,
        'health_status' => 'UNKNOWN',
    ]);

    $adapter = Mockery::mock(PrinterAdapter::class);
    $adapter->shouldReceive('supports')->andReturn(true);
    // send() SHOULD be called because --force bypasses the mutex
    $adapter->shouldReceive('send')->once()->andReturn(PrintResult::ok('OK'));

    $registry = Mockery::mock(PrinterAdapterRegistry::class);
    $registry->shouldReceive('for')->andReturn($adapter);

    $this->app->instance(PrinterAdapterRegistry::class, $registry);

    $exitCode = Artisan::call('serveo:health-check-printers', ['--force' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->not->toContain('Skipping health check')
        ->and($output)->toContain('Printer health check complete');

    $printer->refresh();
    expect($printer->health_status)->toBe('OK');
});
