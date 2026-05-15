<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuItem;
use App\Models\Printer;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');

    $venue = \App\Models\Venue::first();
    $section = Section::firstOrCreate(
        ['venue_id' => $venue->id, 'section_code' => 'TEST'],
        ['name' => 'Test Section', 'sort_order' => 99, 'is_active' => true],
    );
    $this->row = Row::firstOrCreate(
        ['section_id' => $section->id, 'row_code' => 'T1'],
        ['sort_order' => 1, 'is_active' => true],
    );
    for ($i = 1; $i <= 10; $i++) {
        SeatPair::firstOrCreate(
            ['row_id' => $this->row->id, 'pair_sequence' => $i],
            ['seat_a_id' => $i * 2 - 1, 'seat_b_id' => $i * 2, 'is_active' => true],
        );
    }

    $this->group = app(BillingGroupService::class)->open($this->scenario, $this->server);
    app(OccupancyService::class)->assignZone($this->group, $this->row, 1, 5, $this->server);

    // Add an order so there are charges
    $menuItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit(
        $this->group,
        $this->server,
        [['menu_item_id' => $menuItem->id, 'quantity' => 2]],
    );

    // Assign bill printer to cashier
    $billPrinter = Printer::where('printer_type', 'BILL')->first();
    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->cashier->id, 'printer_id' => $billPrinter->id],
        ['is_active' => true],
    );
});

test('checkout screen shows charges and balance', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Billing Groups', 5);

        $browser->visit("/checkout/{$this->group->id}")
            ->waitForText('Checkout', 5)
            ->waitForText('CHARGES', 10)
            ->waitForText('PAID', 10)
            ->waitForText('BALANCE', 10)
            ->waitForText('Print Bill', 10);
    });
});

test('cashier can print bill from checkout', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Billing Groups', 5);

        $browser->visit("/checkout/{$this->group->id}")
            ->waitForText('Checkout', 5)
            ->press('Print Bill')
            ->waitForText('Bill sent to printer.', 5);
    });
});

test('cashier can record partial payment', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Billing Groups', 5);

        $browser->visit("/checkout/{$this->group->id}")
            ->waitForText('Checkout', 5)
            ->type('#payment-amount', 5.00)
            ->type('#payment-label', 'Cash')
            ->press('Record Payment')
            ->waitForText('Payment recorded.', 5);
    });
});

test('cashier can reopen closed group from checkout', function () {
    app(BillingGroupService::class)->close($this->group, $this->cashier);

    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Billing Groups', 5);

        $browser->visit("/checkout/{$this->group->id}")
            ->waitForText('Closed', 5)
            ->press('Reopen')
            ->waitForText('Group reopened.', 5);
    });
});

test('reprint panel lists bills and tickets', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Billing Groups', 5);

        $browser->visit("/reprint/{$this->group->id}")
            ->waitForText('Reprint & Documents', 5)
            ->assertSee('Production Tickets');
    });
});
