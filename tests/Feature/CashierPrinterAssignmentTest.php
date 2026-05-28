<?php

use App\Models\CashierPrinterAssignment;
use App\Models\Printer;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    bootScenario();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function createBillPrinter(string $name = 'Test Bill Printer'): Printer
{
    return Printer::firstOrCreate(
        ['name' => $name],
        [
            'connection_type' => 'NULL',
            'address' => '127.0.0.1',
            'port' => 9100,
            'is_active' => true,
            'health_status' => 'UNKNOWN',
        ]
    );
}

it('assigns bill printer to cashier via updateOrCreate', function () {
    $cashier = makeUser('CASHIER');
    $printer = createBillPrinter();

    CashierPrinterAssignment::updateOrCreate(
        ['user_id' => $cashier->id, 'printer_id' => $printer->id],
        ['is_active' => true]
    );

    $assignment = CashierPrinterAssignment::where('user_id', $cashier->id)->first();
    expect($assignment)->not->toBeNull()
        ->and($assignment->printer_id)->toBe($printer->id)
        ->and($assignment->is_active)->toBeTrue();
});

it('overwrites previous assignment when cashier gets new printer', function () {
    $cashier = makeUser('CASHIER');
    $printer1 = createBillPrinter('Printer 1');
    $printer2 = createBillPrinter('Printer 2');

    // First assignment
    CashierPrinterAssignment::updateOrCreate(
        ['user_id' => $cashier->id],
        ['printer_id' => $printer1->id, 'is_active' => true]
    );

    // Second assignment (overwrites)
    CashierPrinterAssignment::updateOrCreate(
        ['user_id' => $cashier->id],
        ['printer_id' => $printer2->id, 'is_active' => true]
    );

    $assignments = CashierPrinterAssignment::where('user_id', $cashier->id)->get();
    expect($assignments)->toHaveCount(1)
        ->and($assignments->first()->printer_id)->toBe($printer2->id);
});

it('deactivates assignment when is_active set to false', function () {
    $cashier = makeUser('CASHIER');
    $printer = createBillPrinter();

    $assignment = CashierPrinterAssignment::create([
        'user_id' => $cashier->id,
        'printer_id' => $printer->id,
        'is_active' => true,
    ]);

    $assignment->update(['is_active' => false]);

    $assignment->refresh();
    expect($assignment->is_active)->toBeFalse();
});

it('cascade deletes assignment when user is deleted', function () {
    $cashier = makeUser('CASHIER');
    $printer = createBillPrinter();

    CashierPrinterAssignment::create([
        'user_id' => $cashier->id,
        'printer_id' => $printer->id,
        'is_active' => true,
    ]);

    $cashierId = $cashier->id;
    $cashier->delete();

    expect(CashierPrinterAssignment::where('user_id', $cashierId)->exists())->toBeFalse();
});

it('user model has cashierPrinterAssignment relationship', function () {
    $cashier = makeUser('CASHIER');
    $printer = createBillPrinter();

    CashierPrinterAssignment::create([
        'user_id' => $cashier->id,
        'printer_id' => $printer->id,
        'is_active' => true,
    ]);

    $user = User::with('cashierPrinterAssignment')->find($cashier->id);
    expect($user->cashierPrinterAssignment)->not->toBeNull()
        ->and($user->cashierPrinterAssignment->printer_id)->toBe($printer->id);
});
