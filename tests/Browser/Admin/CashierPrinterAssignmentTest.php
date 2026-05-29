<?php

use App\Models\CashierPrinterAssignment;
use App\Models\Printer;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->admin = makeUser('ADMIN', 'admin-cpa-'.bin2hex(random_bytes(2)));
    $this->printer = Printer::where('name', 'Caixa 1')->first();
});

function adminLogin(Browser $browser, string $email): void
{
    $browser->visit('/admin/login')
        ->waitForText('Sign in', 5)
        ->type('input[id="form.email"]', $email)
        ->type('input[id="form.password"]', 'secret')
        ->press('Sign in')
        ->waitForLocation('/admin', 30);
}

test('cashier printer field is visible when editing a user with CASHIER role', function () {
    $cashier = makeUser('CASHIER', 'cashier-vis-'.bin2hex(random_bytes(2)));
    CashierPrinterAssignment::create([
        'user_id' => $cashier->id,
        'printer_id' => $this->printer->id,
        'is_active' => true,
    ]);

    $this->browse(function (Browser $browser) use ($cashier) {
        $browser->driver->manage()->deleteAllCookies();
        adminLogin($browser, $this->admin->email);

        $browser->visit("/admin/users/{$cashier->id}/edit")
            ->waitForText('Name', 10);

        $selector = 'select[id="form.cashier_printer_id"]';
        $browser->assertPresent($selector);

        $value = $browser->script("return document.querySelector('{$selector}').value");
        expect($value[0])->toBe((string) $this->printer->id);
    });
});

test('cashier printer field is hidden when editing a user without CASHIER role', function () {
    $server = makeUser('SERVER', 'server-cpa-'.bin2hex(random_bytes(2)));

    $this->browse(function (Browser $browser) use ($server) {
        $browser->driver->manage()->deleteAllCookies();
        adminLogin($browser, $this->admin->email);

        $browser->visit("/admin/users/{$server->id}/edit")
            ->waitForText('Name', 10);

        $browser->assertMissing('select[id="form.cashier_printer_id"]');
    });
});

test('cashier printer field is hidden on create page when no roles selected', function () {
    $this->browse(function (Browser $browser) {
        $browser->driver->manage()->deleteAllCookies();
        adminLogin($browser, $this->admin->email);

        $browser->visit('/admin/users/create')
            ->waitForText('Name', 10);

        $browser->assertMissing('select[id="form.cashier_printer_id"]');
    });
});

test('cashier printer assignment can be saved through admin edit form', function () {
    $cashier = makeUser('CASHIER', 'cashier-save-'.bin2hex(random_bytes(2)));

    CashierPrinterAssignment::create([
        'user_id' => $cashier->id,
        'printer_id' => $this->printer->id,
        'is_active' => true,
    ]);

    $secondPrinter = Printer::where('name', 'Bar LAN')->first();
    $selector = 'select[id="form.cashier_printer_id"]';

    $this->browse(function (Browser $browser) use ($cashier, $secondPrinter, $selector) {
        $browser->driver->manage()->deleteAllCookies();
        adminLogin($browser, $this->admin->email);

        $browser->visit("/admin/users/{$cashier->id}/edit")
            ->waitForText('Name', 10);

        $browser->assertPresent($selector);

        // Change to a different printer
        $browser->select($selector, (string) $secondPrinter->id)
            ->pause(500);

        $browser->press('Save')
            ->pause(3000);
    });

    $assignment = CashierPrinterAssignment::where('user_id', $cashier->id)
        ->where('is_active', true)
        ->first();
    expect($assignment)->not->toBeNull()
        ->and($assignment->printer_id)->toBe($secondPrinter->id);
});
