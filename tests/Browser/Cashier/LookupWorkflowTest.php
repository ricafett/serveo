<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->cashier = makeUser('CASHIER');
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

test('lookup renders open billing groups', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->visit('/lookup')
            ->waitForText('Billing Groups', 5)
            ->assertSee($this->group->display_code);
    });
});

test('closed groups hidden by default', function () {
    app(BillingGroupService::class)->close($this->group, $this->cashier);

    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->visit('/lookup')
            ->waitForText('Billing Groups', 5)
            ->assertDontSee($this->group->display_code);
    });
});

test('show closed groups when filter enabled', function () {
    app(BillingGroupService::class)->close($this->group, $this->cashier);

    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->visit('/lookup')
            ->waitForText('Billing Groups', 5);

        $browser->check('#show-closed')
            ->waitForText($this->group->display_code, 3);
    });
});

test('search by display code filters results', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->visit('/lookup')
            ->waitForText('Billing Groups', 5);

        $browser->type('#search', $this->group->display_code)
            ->waitForText($this->group->display_code, 3);
    });
});
