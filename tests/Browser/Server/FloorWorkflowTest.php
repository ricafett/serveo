<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->server = makeUser('SERVER');

    // Ensure we have an active session with sections/rows
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
});

test('floor renders sections rows and free ranges', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->visit('/floor')
            ->waitForText('Floor', 5)
            ->assertSee('Test Section')
            ->assertSee('Row T1')
            ->assertSee('1–10');
    });
});

test('clicking free range opens billing group creation modal', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->visit('/floor')
            ->waitForText('Floor', 5);

        $browser->with('main', function (Browser $main) {
            $main->press('1–10')
                ->waitForText('Open Billing Group', 3);
        });
    });
});

test('server can create billing group and see it as occupied', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->visit('/floor')
            ->waitForText('Floor', 5)
            ->pause(300);

        // Click free range to open modal
        $browser->with('main', function (Browser $main) {
            $main->press('1–10')
                ->waitForText('Open Billing Group', 3)
                ->type('#name', 'Test Group')
                ->type('#cover-count', 4)
                ->press('Open Billing Group')
                ->waitForText('Occupied Zones', 5);
        });

        // Navigate back to floor and verify occupied state
        $browser->visit('/floor')
            ->waitForText('Floor', 5)
            ->assertSee('Open Billing Groups');
    });
});

test('billing group detail shows zones orders and totals', function () {
    $group = app(BillingGroupService::class)->open($this->scenario, $this->server);
    app(OccupancyService::class)->assignZone($group, $this->row, 1, 5, $this->server);

    $this->browse(function (Browser $browser) use ($group) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        $browser->visit("/billing-groups/{$group->id}")
            ->waitForText($group->display_code, 5)
            ->waitForText('Occupied Zones', 10)
            ->waitForText('CHARGES', 10)
            ->waitForText('BALANCE', 10)
            ->waitForText('Add Order', 10);
    });
});

test('closed billing group hides add order button', function () {
    $group = app(BillingGroupService::class)->open($this->scenario, $this->server);
    app(OccupancyService::class)->assignZone($group, $this->row, 1, 5, $this->server);

    $cashier = makeUser('CASHIER');
    app(BillingGroupService::class)->close($group, $cashier);

    $this->browse(function (Browser $browser) use ($group) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        $browser->visit("/billing-groups/{$group->id}")
            ->waitForText('Closed', 5)
            ->assertDontSee('Add Order')
            ->waitForText('CHARGES', 10)
            ->waitForText('BALANCE', 10);
    });
});

test('floor redirects to dashboard when no open session', function () {
    ServiceSession::where('status', 'OPEN')->update(['status' => 'CLOSED']);

    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->visit('/floor')
            ->waitForText('No open service session', 5);
    });
});
