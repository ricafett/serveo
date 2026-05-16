<?php

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Tables to truncate between Dusk tests to keep state clean
     * without dropping the schema (required because the server runs
     * in a separate process and shares the SQLite file).
     */
    protected array $truncatableTables = [
        'accounting_exports',
        'audit_events',
        'billing_documents',
        'billing_groups',
        'cashier_printer_assignments',
        'occupied_zones',
        'order_headers',
        'order_items',
        'payment_records',
        'print_jobs',
        'production_tickets',
        'service_sessions',
        'model_has_permissions',
        'model_has_roles',
        'users',
    ];

    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure Dusk tests use the shared SQLite file, not :memory:.
        config(['database.connections.sqlite.database' => database_path('dusk.sqlite')]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        // Truncate operational tables so each test starts clean,
        // while keeping reference data (roles, permissions, venue, menu, printers).
        DB::statement('PRAGMA foreign_keys = OFF');
        foreach ($this->truncatableTables as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('PRAGMA foreign_keys = ON');

        // Clear translation cache so locale changes are picked up
        \Illuminate\Support\Facades\Cache::flush();

        // Force default locale to en-US for predictable test assertions
        app()->setLocale('en-US');
        if (app()->bound('translator')) {
            app('translator')->setLocale('en-US');
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        // Use Microsoft Edge if Google Chrome is not available (common on Windows dev boxes)
        $chromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe';
        $edgePath = 'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe';
        if (! file_exists($chromePath) && file_exists($edgePath)) {
            $options->setBinary($edgePath);
        }

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }

    /**
     * Seed the baseline scenario and return the active service session.
     */
    protected function scenario(): \App\Models\ServiceSession
    {
        return bootScenario();
    }
}
