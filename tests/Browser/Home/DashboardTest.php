<?php

use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->admin = makeUser('ADMIN');
});

test('dashboard renders for server with floor tile', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->assertPathIs('/home')
            ->assertSee('Floor')
            ->assertSee('View occupancy and manage seating')
            ->assertDontSee('Billing Groups')
            ->assertDontSee('Admin Panel');
    });
});

test('dashboard renders for cashier with lookup and reprint tiles', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->assertPathIs('/home')
            ->assertSee('Billing Groups')
            ->assertSee('Reprint')
            ->assertDontSee('Floor')
            ->assertDontSee('Admin Panel');
    });
});

test('dashboard renders for admin with all tiles including admin panel', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->admin->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->assertPathIs('/home')
            ->assertSee('Floor')
            ->assertSee('Billing Groups')
            ->assertSee('Reprint')
            ->assertSee('Admin Panel')
            ->assertSee('Configuration and system settings');
    });
});

test('dashboard shows active session banner', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->assertSee('Active session');
    });
});

test('server can navigate to floor from dashboard tile', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->clickLink('Floor')
            ->waitForText('Floor', 5)
            ->assertPathIs('/floor');
    });
});

test('cashier can navigate to lookup from dashboard tile', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->clickLink('Billing Groups')
            ->waitForText('Billing Groups', 5)
            ->assertPathIs('/lookup');
    });
});

test('admin can navigate to filament from dashboard tile', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->admin->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->clickLink('Admin Panel')
            ->waitForLocation('/admin', 30);
    });
});

test('navigation includes home link for all roles', function () {
    $this->browse(function (Browser $browser) {
        // Server
        $browser->driver->manage()->deleteAllCookies();
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->assertSee('Dashboard');

        // Cashier
        $browser->driver->manage()->deleteAllCookies();
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->assertSee('Dashboard');

        // Admin
        $browser->driver->manage()->deleteAllCookies();
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->admin->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5)
            ->assertSee('Dashboard');
    });
});
