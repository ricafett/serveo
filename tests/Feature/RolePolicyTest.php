<?php

beforeEach(function () {
    bootScenario();
});

it('admin user can access the Filament admin panel', function () {
    $admin = makeUser('ADMIN');
    expect($admin->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))->toBeTrue();
});

it('cashier and server users can access the Filament admin panel', function () {
    expect(makeUser('CASHIER')->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))->toBeTrue();
    expect(makeUser('SERVER')->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))->toBeTrue();
});

it('non-interactive output roles cannot access the panel', function () {
    expect(makeUser('KITCHEN_OUTPUT')->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))->toBeFalse();
    expect(makeUser('BAR_OUTPUT')->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))->toBeFalse();
});

it('inactive users cannot access the panel even with role', function () {
    $admin = makeUser('ADMIN');
    $admin->update(['is_active' => false]);
    expect($admin->refresh()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))->toBeFalse();
});

it('servers have order.create permission and cashiers do not', function () {
    expect(makeUser('SERVER')->hasPermissionTo('order.create'))->toBeTrue();
    expect(makeUser('CASHIER')->hasPermissionTo('order.create'))->toBeFalse();
});

it('cashiers have payment.record permission and servers do not', function () {
    expect(makeUser('CASHIER')->hasPermissionTo('payment.record'))->toBeTrue();
    expect(makeUser('SERVER')->hasPermissionTo('payment.record'))->toBeFalse();
});
