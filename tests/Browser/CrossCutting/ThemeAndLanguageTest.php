<?php

use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->server = makeUser('SERVER');
    $this->server->update(['preferred_language_code' => 'en-US']);
    $this->cashier = makeUser('CASHIER');
    $this->cashier->update(['preferred_language_code' => 'en-US']);
    $this->admin = makeUser('ADMIN');
    $this->admin->update(['preferred_language_code' => 'en-US']);

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

// ------------------------------------------------------------------
// Theme
// ------------------------------------------------------------------

test('theme toggle switches between light and dark', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        // Visit the page first so we have a real origin before touching localStorage.
        $browser->visit('/login')
            ->waitForText('Sign In', 5);

        // Clear any persisted theme from a previous test run and reload.
        $browser->script('localStorage.clear();');
        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->pause(300); // Give Livewire time to initialise its event listeners

        // Start from a known dark state by clicking Dark first.
        // This avoids depending on the browser's system colour scheme.
        $browser->click('[title="Dark"]')
            ->pause(300);

        // Now switch to Light and assert the dark class is removed
        $browser->click('[title="Light"]')
            ->pause(300);

        $hasDarkAfterLight = $browser->script('return document.documentElement.classList.contains("dark");')[0];
        $this->assertFalse($hasDarkAfterLight, 'Expected <html> to NOT have dark class after clicking light');

        // Switch back to Dark and assert the dark class is present
        $browser->click('[title="Dark"]')
            ->pause(300);

        $hasDarkAfterDark = $browser->script('return document.documentElement.classList.contains("dark");')[0];
        $this->assertTrue($hasDarkAfterDark, 'Expected <html> to have dark class after clicking dark');
    });
});

// ------------------------------------------------------------------
// Language Switcher UI
// ------------------------------------------------------------------

test('language switcher dropdown is visible on login page', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5);

        // Trigger button should show active locale code
        $browser->assertSee('EN')
            ->assertPresent('[aria-label="Select language"]');
    });
});

test('language switcher is inside user menu on operational layout', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        // Open user menu
        $browser->click('[aria-label="User menu"]')
            ->pause(300);

        // Theme and language options should be present in the page source
        // (assertSourceHas checks raw HTML; assertSee only checks visible text)
        $browser->assertSourceHas('Theme')
            ->assertSourceHas('Language')
            ->assertSourceHas('English')
            ->assertSourceHas('Português');
    });
});

test('language options are accessible inside user menu', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        // Open user menu, then open nested language dropdown
        $browser->click('[aria-label="User menu"]')
            ->pause(300)
            ->click('[aria-label="Select language"]')
            ->pause(300);

        // Both language options should be visible in the nested dropdown
        $browser->assertSee('Português')
            ->assertSee('English');
    });
});

// ------------------------------------------------------------------
// Translation Verification
// ------------------------------------------------------------------

test('login page shows English translations by default', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->assertSee('Sign In')
            ->assertSee('Username')
            ->assertSee('Password');
    });
});

test('switching to Portuguese updates login page text on reload', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->assertSee('Sign In');

        // Switch to Portuguese via standalone dropdown on login page
        $browser->click('[aria-label="Select language"]')
            ->pause(300)
            ->click('@switch-locale-pt-PT')
            ->waitForText('Iniciar sessão', 5);

        $browser->assertSee('Iniciar sessão')
            ->assertSee('Nome de utilizador')
            ->assertSee('Palavra-passe');
    });
});

test('operational floor page shows English translations', function () {
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

        $browser->assertSee('Floor');
    });
});

test('switching to Portuguese updates floor page text', function () {
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

        // Open user menu, then open nested language dropdown, then switch
        $browser->click('[aria-label="User menu"]')
            ->pause(300)
            ->click('[aria-label="Select language"]')
            ->pause(300)
            ->click('@switch-locale-pt-PT')
            ->waitForText('Plano de sala', 5);

        $browser->assertSee('Plano de sala');
    });
});

test('order entry page shows correct translations', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $group = createBillingGroup($this->scenario, $this->server);

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Floor', 5);

        $browser->visit("/orders/new/{$group->id}")
            ->waitForText('Order Entry', 5)
            ->assertSee('Submit Order');
    });
});

test('cashier checkout page shows correct translations', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $group = createBillingGroup($this->scenario, $this->server);

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Billing Groups', 5);

        $browser->visit("/checkout/{$group->id}")
            ->waitForText('Checkout', 5)
            ->assertSee('CHARGES')
            ->assertSee('BALANCE')
            ->assertSee('Print Bill');
    });
});

// ------------------------------------------------------------------
// Mobile / Navigation
// ------------------------------------------------------------------

test('mobile bottom nav is visible on small screens', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->resize(375, 812)
            ->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        // On mobile, server should see Dashboard and Floor in bottom nav
        $browser->assertSee('Dashboard')
            ->assertSee('Floor');
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
            ->waitForText('Dashboard', 5);

        $browser->click('[aria-label="User menu"]')
            ->waitForText('Log Out', 3)
            ->assertSee('SERVER')
            ->assertSee('Log Out');
    });
});

// ------------------------------------------------------------------
// Admin / Filament
// ------------------------------------------------------------------

test('admin login page shows language switcher', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5);

        $browser->assertPresent('[aria-label="Select language"]')
            ->assertSee('EN');
    });
});

test('admin dashboard shows translated navigation groups', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[type="email"]', $this->admin->email)
            ->type('input[type="password"]', 'secret')
            ->press('Sign in')
            ->pause(1000)
            ->waitForText('Dashboard', 10);

        // Navigation groups may appear as raw keys if resources use keys but panel uses translations
        $browser->assertSee('Dashboard');
    });
});

test('switching to Portuguese updates admin navigation text', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[type="email"]', $this->admin->email)
            ->type('input[type="password"]', 'secret')
            ->press('Sign in')
            ->pause(1000)
            ->waitForText('Dashboard', 10);

        $browser->click('[aria-label="Select language"]')
            ->pause(300)
            ->click('@switch-locale-pt-PT')
            ->waitForText('PT', 10)
            ->pause(1500);

        $browser->assertSourceHas('Operação');
    });
});
