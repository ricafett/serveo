<?php

use App\Models\CashierPrinterAssignment;
use App\Models\MenuItem;
use App\Models\Printer;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->cashier = makeUser('CASHIER');
    $this->voucherItem = MenuItem::where('display_name', 'Bacalhau')->firstOrFail();
    $this->voucherItem->update(['is_voucher_enabled' => true]);

    $printer = Printer::where('is_active', true)->firstOrFail();
    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->cashier->id, 'printer_id' => $printer->id],
        ['is_active' => true],
    );
});

test('cashier can complete a sale and see success feedback', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 10)
            ->type('username', $this->cashier->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        $browser->visit('/sales')
            ->waitForText('Sales', 10)
            ->press($this->voucherItem->display_name)
            ->waitForText('Pay and print vouchers', 10)
            ->type('#sale-payment-amount', '18')
            ->type('#sale-payment-label', 'Cash')
            ->check('#sale-print-receipt')
            ->press('Pay and print vouchers')
            ->waitForText('completed', 30);
    });
});
