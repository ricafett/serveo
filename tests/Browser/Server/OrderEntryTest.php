<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\MenuItem;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->server = makeUser('SERVER');

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
});

test('order entry page loads for open billing group', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5);

        $browser->visit("/orders/new/{$this->group->id}")
            ->waitForText('Order Entry', 5)
            ->assertSee($this->group->display_code);
    });
});

test('server can add items and submit order', function () {
    $menuItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $this->browse(function (Browser $browser) use ($menuItem) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5);

        $browser->visit("/orders/new/{$this->group->id}")
            ->waitForText('Order Entry', 5);

        // Ensure the correct category is selected so the item is visible
        $browser->press($menuItem->category->display_name)
            ->waitForText($menuItem->display_name, 5);

        $browser->press($menuItem->display_name)
            ->waitForText('Cart', 3);

        $browser->press('Submit Order')
            ->waitForText('Order submitted', 5);
    });
});

test('submitted order appears in billing group detail', function () {
    $menuItem = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit(
        $this->group,
        $this->server,
        [['menu_item_id' => $menuItem->id, 'quantity' => 2]],
    );

    $this->browse(function (Browser $browser) use ($menuItem) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5);

        $browser->visit("/billing-groups/{$this->group->id}")
            ->waitForText($menuItem->display_name, 5)
            ->assertSee('SUBMITTED');
    });
});

test('order entry blocked for closed billing group', function () {
    $cashier = makeUser('CASHIER');
    app(BillingGroupService::class)->close($this->group, $cashier);

    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5);

        $browser->visit("/orders/new/{$this->group->id}")
            ->waitForText('Closed', 5);
    });
});
