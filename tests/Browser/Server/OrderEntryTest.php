<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\MenuItem;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\Venue;
use Laravel\Dusk\Browser;

function clearWebStorage(Browser $browser): void
{
    $browser->script('window.sessionStorage.clear(); window.localStorage.clear();');
}

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->server = makeUser('SERVER');

    $venue = Venue::first();
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
            ->waitForText('Dashboard', 5);

        clearWebStorage($browser);

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
            ->waitForText('Dashboard', 5);

        clearWebStorage($browser);

        $browser->visit("/orders/new/{$this->group->id}")
            ->waitForText('Order Entry', 5);

        // Ensure the correct category is selected so the item is visible
        $browser->press($menuItem->category->display_name)
            ->waitForText($menuItem->display_name, 5);

        $browser->press($menuItem->display_name)
            ->pause(500);

        // Verify item was added to cart
        $browser->assertDontSee('No items added.')
            ->pause(500)
            ->press('Submit Order')
            ->waitForText('Order submitted', 20);
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
            ->waitForText('Dashboard', 5);

        clearWebStorage($browser);

        $browser->visit("/billing-groups/{$this->group->id}")
            ->waitForText($menuItem->display_name, 5)
            ->assertSee('Submitted');
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
            ->waitForText('Dashboard', 5);

        clearWebStorage($browser);

        $browser->visit("/orders/new/{$this->group->id}")
            ->waitForText('Closed', 5);
    });
});

test('dirty order shows custom leave modal from header back button', function () {
    $menuItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $this->browse(function (Browser $browser) use ($menuItem) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        clearWebStorage($browser);

        $browser->visit("/orders/new/{$this->group->id}")
            ->waitForText('Order Entry', 5)
            ->press($menuItem->category->display_name)
            ->waitForText($menuItem->display_name, 5)
            ->press($menuItem->display_name)
            ->pause(500)
            ->click('@order-back-button')
            ->waitForText('Unsaved Order', 5)
            ->assertPathIs("/orders/new/{$this->group->id}");

        $display = $browser->script("return document.querySelector('[dusk=\"leave-confirm-modal\"]').style.display");
        expect($display[0])->toBe('');
    });
});

test('dirty order best effort intercepts browser back and stays on page', function () {
    $menuItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $this->browse(function (Browser $browser) use ($menuItem) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        clearWebStorage($browser);

        $browser->visit("/orders/new/{$this->group->id}")
            ->waitForText('Order Entry', 5)
            ->press($menuItem->category->display_name)
            ->waitForText($menuItem->display_name, 5)
            ->press($menuItem->display_name)
            ->pause(500);

        $browser->script('window.history.back()');

        $browser->waitForText('Unsaved Order', 5)
            ->assertPathIs("/orders/new/{$this->group->id}");
    });
});

test('order entry can restore a draft cart from session storage', function () {
    $menuItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $this->browse(function (Browser $browser) use ($menuItem) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        clearWebStorage($browser);

        $browser->script("window.sessionStorage.setItem('serveo.order-entry.draft.{$this->group->id}', JSON.stringify({ cart: [{ cart_key: 'draft-key', menu_item_id: {$menuItem->id}, display_name: '{$menuItem->display_name}', unit_price: {$menuItem->unit_price}, quantity: 2, route_type: '{$menuItem->category->route_type}', variant_name: null, modifier_name: null, note: null }], savedAt: Date.now() }))");

        $browser->visit("/orders/new/{$this->group->id}")
            ->waitForText('Order Entry', 5)
            ->waitForText('Restore Draft Order', 5)
            ->click('@restore-draft')
            ->pause(500)
            ->assertSee($menuItem->display_name);

        $quantity = $browser->script("return document.querySelector('[x-data]')._x_dataStack[0].cart[0].quantity");
        expect($quantity[0])->toBe(2);
    });
});
