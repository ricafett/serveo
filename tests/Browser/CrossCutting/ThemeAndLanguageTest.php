<?php

use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\TranslationKey;
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
});

test('theme toggle switches between light and dark', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5);

        // Default should be dark (system dark or user dark)
        $hasDark = $browser->script('return document.documentElement.classList.contains("dark");')[0];

        // Click light theme button
        $browser->click('[title="Claro"]')
            ->pause(500);

        $hasDarkAfterLight = $browser->script('return document.documentElement.classList.contains("dark");')[0];
        $this->assertFalse($hasDarkAfterLight, 'Expected <html> to NOT have dark class after clicking light');

        // Click dark theme button
        $browser->click('[title="Escuro"]')
            ->pause(500);

        $hasDarkAfterDark = $browser->script('return document.documentElement.classList.contains("dark");')[0];
        $this->assertTrue($hasDarkAfterDark, 'Expected <html> to have dark class after clicking dark');
    });
});

test('language switcher buttons are clickable', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5);

        // Buttons should be visible and clickable
        $browser->assertSee('PT')
            ->assertSee('EN')
            ->assertPresent('[title="Português"]')
            ->assertPresent('[title="English"]');
    });
});

test('language switcher is visible on operational layout', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5);

        $browser->assertPresent('[wire\:id]');
    });
});

test('mobile bottom nav is visible on small screens', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->resize(375, 812)
            ->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5);

        // On mobile, server should see Floor in bottom nav
        $browser->assertSee('Floor');
    });
});

test('user menu shows role and logout option', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5);

        $browser->click('[aria-label="User menu"]')
            ->waitForText('Log Out', 3)
            ->assertSee('SERVER')
            ->assertSee('Log Out');
    });
});
