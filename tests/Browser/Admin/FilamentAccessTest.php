<?php

use Laravel\Dusk\Browser;

test('admin can log in and access filament dashboard', function () {
    $this->scenario();
    $admin = makeUser('ADMIN');

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);
    });
});

test('non-admin cannot access filament admin', function () {
    $this->scenario();
    $server = makeUser('SERVER');

    $this->browse(function (Browser $browser) use ($server) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $server->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForText('Sign in', 10);
    });
});

test('admin can navigate to users resource', function () {
    $this->scenario();
    $admin = makeUser('ADMIN');

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit('/admin/users')
            ->waitForLocation('/admin/users', 15)
            ->assertPathBeginsWith('/admin/users');
    });
});

test('admin can navigate to printers resource', function () {
    $this->scenario();
    $admin = makeUser('ADMIN');

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit('/admin/printers')
            ->waitForLocation('/admin/printers', 15)
            ->assertPathBeginsWith('/admin/printers');
    });
});

test('admin can navigate to event log resource', function () {
    $this->scenario();
    $admin = makeUser('ADMIN');

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit('/admin/audit-events')
            ->waitForLocation('/admin/audit-events', 15)
            ->assertPathBeginsWith('/admin/audit-events');
    });
});
