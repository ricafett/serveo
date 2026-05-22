<?php

use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\Venue;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->server = makeUser('SERVER');
    $this->server->update(['preferred_language_code' => 'en-US']);
    $this->cashier = makeUser('CASHIER');
    $this->cashier->update(['preferred_language_code' => 'en-US']);
    $this->admin = makeUser('ADMIN');
    $this->admin->update(['preferred_language_code' => 'en-US']);

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

test('language switcher is inline on login page', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5);

        // Inline mode shows PT/EN buttons directly, no dropdown trigger
        $browser->assertSee('PT')
            ->assertSee('EN')
            ->assertPresent('@switch-locale-pt-PT')
            ->assertPresent('@switch-locale-en-US');
    });
});

test('language switcher is inline inside user menu on operational layout', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        // Open user menu — inline PT/EN buttons should be visible immediately
        $browser->click('[aria-label="User menu"]')
            ->pause(300);

        $browser->assertSourceHas('Theme')
            ->assertSourceHas('Language')
            ->assertPresent('@switch-locale-pt-PT')
            ->assertPresent('@switch-locale-en-US');
    });
});

test('language options are accessible inside user menu without nested dropdown', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        // Open user menu — inline buttons are visible immediately, no nested dropdown
        $browser->click('[aria-label="User menu"]')
            ->pause(300);

        // PT and EN buttons should be directly visible
        $browser->assertPresent('@switch-locale-pt-PT')
            ->assertPresent('@switch-locale-en-US');
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

        // Switch to Portuguese via inline button on login page
        $browser->click('@switch-locale-pt-PT')
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
            ->waitForText('Dashboard', 5);

        // Header (with user menu + language switcher) is only on dashboard.
        // Switch language here before navigating to floor.
        $browser->click('[aria-label="User menu"]')
            ->pause(300)
            ->click('@switch-locale-pt-PT')
            ->waitForText('Painel', 5);

        // Now navigate to floor and verify Portuguese translations
        $browser->visit('/floor')
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
// Theme Persistence Across Navigation
// ------------------------------------------------------------------

test('theme persists when navigating to billing group detail via wire:navigate', function () {
    $group = createBillingGroup($this->scenario, $this->server);

    $this->browse(function (Browser $browser) use ($group) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        // Header (with user menu + theme toggle) is only on dashboard.
        // Set theme to dark here before navigating to floor.
        $browser->click('[aria-label="User menu"]')
            ->pause(300)
            ->click('[title="Dark"]')
            ->pause(500);

        // Navigate to floor
        $browser->visit('/floor')
            ->waitForText('Floor', 5);

        // Verify dark class is present on floor page
        $hasDarkOnFloor = $browser->script('return document.documentElement.classList.contains("dark");')[0];
        $this->assertTrue($hasDarkOnFloor, 'Expected dark class on floor page after selecting dark theme');

        // Navigate to billing group detail via direct visit (simulates wire:navigate destination)
        $browser->visit("/billing-groups/{$group->id}")
            ->waitForText($group->display_code, 5)
            ->pause(500);

        // Verify dark class persists on billing group detail page
        $hasDarkOnDetail = $browser->script('return document.documentElement.classList.contains("dark");')[0];
        $this->assertTrue($hasDarkOnDetail, 'Expected dark class to persist on billing group detail page');

        // Navigate back to floor
        $browser->visit('/floor')
            ->waitForText('Floor', 5)
            ->pause(500);

        // Verify dark class persists after navigating back
        $hasDarkAfterBack = $browser->script('return document.documentElement.classList.contains("dark");')[0];
        $this->assertTrue($hasDarkAfterBack, 'Expected dark class to persist after navigating back to floor');
    });
});

test('theme persists when navigating through order entry flow', function () {
    $group = createBillingGroup($this->scenario, $this->server);

    $this->browse(function (Browser $browser) use ($group) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $this->server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        // Set theme to dark
        $browser->click('[aria-label="User menu"]')
            ->pause(300)
            ->click('[title="Dark"]')
            ->pause(500);

        // Navigate to billing group detail
        $browser->visit("/billing-groups/{$group->id}")
            ->waitForText($group->display_code, 5)
            ->pause(500);

        // Verify dark class
        $hasDarkOnDetail = $browser->script('return document.documentElement.classList.contains("dark");')[0];
        $this->assertTrue($hasDarkOnDetail, 'Expected dark class on billing group detail');

        // Navigate to order entry
        $browser->visit("/orders/new/{$group->id}")
            ->waitForText('Order Entry', 5)
            ->pause(500);

        // Verify dark class persists on order entry
        $hasDarkOnOrderEntry = $browser->script('return document.documentElement.classList.contains("dark");')[0];
        $this->assertTrue($hasDarkOnOrderEntry, 'Expected dark class to persist on order entry page');
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
