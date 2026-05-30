<?php

use Filament\Facades\Filament;

beforeEach(function () {
    bootScenario();
});

it('admin user can access the Filament admin panel', function () {
    $admin = makeUser('ADMIN');
    expect($admin->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('cashier and server users cannot access the Filament admin panel', function () {
    expect(makeUser('CASHIER')->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
    expect(makeUser('SERVER')->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('non-interactive output roles cannot access the panel', function () {
    expect(makeUser('KITCHEN_OUTPUT')->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
    expect(makeUser('BAR_OUTPUT')->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('inactive users cannot access the panel even with role', function () {
    $admin = makeUser('ADMIN');
    $admin->update(['is_active' => false]);
    expect($admin->refresh()->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('servers and cashiers have order.create permission', function () {
    expect(makeUser('SERVER')->hasPermissionTo('order.create'))->toBeTrue();
    expect(makeUser('CASHIER')->hasPermissionTo('order.create'))->toBeTrue();
});

it('cashiers have payment.record permission and servers do not', function () {
    expect(makeUser('CASHIER')->hasPermissionTo('payment.record'))->toBeTrue();
    expect(makeUser('SERVER')->hasPermissionTo('payment.record'))->toBeFalse();
});
