<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\Venue;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->cashier = makeUser('CASHIER');
    $this->server = makeUser('SERVER');
    $this->assignedServer = makeUser('SERVER', 'floor-assigned-server-'.bin2hex(random_bytes(3)));

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
});

test('cashier can access floor and sees floor navigation', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 10)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->visit('/floor')
            ->waitForText('Floor', 5)
            ->assertSee('TESTT101');
    });
});

test('cashier creating group from floor requires assigned server field', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 10)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->visit('/floor')
            ->waitForText('Floor', 5)
            ->press('TESTT101')
            ->waitForText('Open Billing Group', 5)
            ->waitFor('#floor-assigned-server-id', 5)
            ->type('#name', 'Cashier Group')
            ->press('Open Billing Group')
            ->waitForText('Assigned server is required.', 5);
    });
});

test('cashier can create billing group from floor with assigned server', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 10)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->visit('/floor')
            ->waitForText('Floor', 5)
            ->press('TESTT101')
            ->waitForText('Open Billing Group', 5)
            ->waitFor('#floor-assigned-server-id', 5)
            ->type('#name', 'Cashier Group')
            ->select('#floor-assigned-server-id', (string) $this->assignedServer->id)
            ->press('Open Billing Group')
            ->waitForText('Occupied Zones', 10)
            ->waitForText($this->assignedServer->name, 10);
    });
});
