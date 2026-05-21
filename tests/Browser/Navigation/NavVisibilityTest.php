<?php

use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->server = makeUser('SERVER');
});

test('header is present on dashboard page', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->assertPathIs('/home')
            // Header with logo and user dropdown must be present on dashboard
            ->assertPresent('header.sticky.top-0');
    });
});

test('header is absent on floor page', function () {
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
            ->assertPathIs('/floor')
            // Header must NOT be present on task-focused pages
            ->assertMissing('header.sticky.top-0');
    });
});

test('bottom nav is present on floor page', function () {
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
            ->assertPathIs('/floor')
            // Bottom/sidebar nav must be present on all pages (not gated)
            ->assertPresent('nav.fixed');
    });
});
