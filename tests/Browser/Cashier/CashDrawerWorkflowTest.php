<?php

use App\Models\CashierPrinterAssignment;
use App\Models\Printer;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->cashier = makeUser('CASHIER');

    $printer = Printer::where('is_active', true)->firstOrFail();
    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->cashier->id, 'printer_id' => $printer->id],
        ['is_active' => true],
    );
});

test('cashier can view cash drawer page', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 10)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        $browser->visit('/cash-drawer')
            ->waitForText('Cash Drawer', 10)
            ->assertSee('Cash Drawer');
    });
});

test('cashier can record a cash in movement', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 10)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        $browser->visit('/cash-drawer')
            ->waitForText('Cash Drawer', 10)
            ->press('Record Movement')
            ->waitForText('New Movement', 5)
            ->type('#cashdrawer-amount', '200')
            ->type('#cashdrawer-label', 'Opening Float')
            ->press('Record Cash In')
            ->waitForText('Movement recorded successfully', 15)
            ->assertSee('200.00');
    });
});

test('cashier can record a cash out movement', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 10)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        // First record a cash in
        $browser->visit('/cash-drawer')
            ->waitForText('Cash Drawer', 10)
            ->press('Record Movement')
            ->waitForText('New Movement', 5)
            ->type('#cashdrawer-amount', '500')
            ->type('#cashdrawer-label', 'Opening Float')
            ->press('Record Cash In')
            ->waitForText('Movement recorded successfully', 15);

        // Switch to Cash Out tab and record
        $browser->press('Record Movement')
            ->waitForText('New Movement', 5)
            ->press('Cash Out')
            ->pause(300)
            ->type('#cashdrawer-amount', '100')
            ->type('#cashdrawer-label', 'Bank Deposit')
            ->press('Record Cash Out')
            ->waitForText('Movement recorded successfully', 15)
            ->assertSee('400.00');
    });
});

test('cash drawer navigation item is visible for cashier', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 10)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        $browser->assertSee('Cash Drawer');
    });
});
