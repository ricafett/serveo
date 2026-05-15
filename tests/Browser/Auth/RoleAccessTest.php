<?php

use Laravel\Dusk\Browser;

test('guest cannot access protected routes', function () {
    $this->scenario();

    $this->browse(function (Browser $browser) {
        $browser->visit('/floor')
            ->waitForLocation('/login', 5)
            ->assertPathIs('/login')
            ->waitForText('Sign In', 5);

        $browser->visit('/lookup')
            ->waitForLocation('/login', 5)
            ->assertPathIs('/login');

        $browser->visit('/billing-groups/1')
            ->waitForLocation('/login', 5)
            ->assertPathIs('/login');
    });
});

test('server cannot access cashier routes', function () {
    $this->scenario();

    $server = makeUser('SERVER');

    $this->browse(function (Browser $browser) use ($server) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5);

        $browser->visit('/lookup')
            ->waitForText('403', 5);

        $browser->visit('/checkout/1')
            ->waitForText('403', 5);
    });
});

test('cashier cannot access server routes', function () {
    $this->scenario();

    $cashier = makeUser('CASHIER');

    $this->browse(function (Browser $browser) use ($cashier) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Billing Groups', 5);

        $browser->visit('/floor')
            ->waitForText('403', 5);

        $browser->visit('/orders/new/1')
            ->waitForText('403', 5);
    });
});

test('navigation shows correct items per role', function () {
    $this->scenario();

    $server = makeUser('SERVER');
    $cashier = makeUser('CASHIER');
    $admin = makeUser('ADMIN');

    $this->browse(function (Browser $browser) use ($server, $cashier, $admin) {
        // Server sees Floor nav
        $browser->driver->manage()->deleteAllCookies();
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5)
            ->assertSee('Floor')
            ->assertDontSee('Lookup');

        // Cashier sees Lookup nav
        $browser->driver->manage()->deleteAllCookies();
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Billing Groups', 5)
            ->assertSee('Lookup')
            ->assertDontSee('Floor');

        // Admin sees both Floor and Lookup nav
        $browser->driver->manage()->deleteAllCookies();
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $admin->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);
    });
});
