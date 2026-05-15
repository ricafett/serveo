<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;

test('login with valid credentials redirects to role-appropriate page', function () {
    $this->scenario();

    $server = makeUser('SERVER');
    $cashier = makeUser('CASHIER');
    $admin = makeUser('ADMIN');

    $this->browse(function (Browser $browser) use ($server, $cashier, $admin) {
        // Server lands on Floor
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5)
            ->assertPathIs('/floor');

        // Cashier lands on Lookup
        $browser->driver->manage()->deleteAllCookies();
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Billing Groups', 5)
            ->assertPathIs('/lookup');

        // Admin lands on Filament dashboard
        $browser->driver->manage()->deleteAllCookies();
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $admin->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);
    });
});

test('login with invalid credentials shows error', function () {
    $this->scenario();

    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', 'nonexistent')
            ->type('password', 'wrong')
            ->press('Sign In')
            ->waitForText('Invalid credentials', 5);
    });
});

test('inactive user cannot log in', function () {
    $this->scenario();

    $user = User::create([
        'username' => 'inactive-user',
        'name' => 'Inactive',
        'email' => 'inactive@example.test',
        'password' => Hash::make('secret'),
        'is_active' => false,
    ]);
    $user->assignRole('SERVER');

    $this->browse(function (Browser $browser) use ($user) {
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $user->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Account is inactive', 5);
    });
});

test('logout clears session and redirects to login', function () {
    $this->scenario();

    $server = makeUser('SERVER');

    $this->browse(function (Browser $browser) use ($server) {
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5);

        $browser->click('[aria-label="User menu"]')
            ->waitForText('Log Out', 3)
            ->press('Log Out')
            ->waitForText('Sign In', 5)
            ->assertPathIs('/login');
    });
});
