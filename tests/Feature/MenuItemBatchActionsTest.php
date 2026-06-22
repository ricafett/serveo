<?php

use App\Filament\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Models\AuditEvent;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CoreSeeder::class);

    $this->admin = makeUser('ADMIN');
    $this->actingAs($this->admin);

    $this->category = MenuCategory::create([
        'code' => 'BATCH-TEST',
        'display_name' => 'Batch Test',
        'route_type' => 'KITCHEN',
        'sort_order' => 1,
        'is_active' => true,
    ]);
});

function makeBatchMenuItems(MenuCategory $category, array $attributes = []): \Illuminate\Database\Eloquent\Collection
{
    return new \Illuminate\Database\Eloquent\Collection(collect(range(1, 2))->map(function (int $index) use ($category, $attributes) {
        return MenuItem::create(array_merge([
            'menu_category_id' => $category->id,
            'display_name' => 'Batch Item '.$index.' '.str()->random(5),
            'unit_price' => 10 + $index,
            'is_active' => false,
            'is_voucher_enabled' => false,
        ], $attributes));
    })->all());
}

it('enables selected menu items in bulk', function () {
    $items = makeBatchMenuItems($this->category);

    Livewire::test(ListMenuItems::class)
        ->assertCanSeeTableRecords($items)
        ->selectTableRecords($items)
        ->callAction(TestAction::make('enableSelected')->table()->bulk());

    expect(MenuItem::whereKey($items->modelKeys())->where('is_active', true)->count())->toBe(2)
        ->and(AuditEvent::where('event_type', 'MENU_ITEMS_BULK_ENABLED')->exists())->toBeTrue();
});

it('disables selected menu items in bulk', function () {
    $items = makeBatchMenuItems($this->category, ['is_active' => true]);

    Livewire::test(ListMenuItems::class)
        ->assertCanSeeTableRecords($items)
        ->selectTableRecords($items)
        ->callAction(TestAction::make('disableSelected')->table()->bulk());

    expect(MenuItem::whereKey($items->modelKeys())->where('is_active', false)->count())->toBe(2)
        ->and(AuditEvent::where('event_type', 'MENU_ITEMS_BULK_DISABLED')->exists())->toBeTrue();
});

it('enables vouchers on selected menu items in bulk', function () {
    $items = makeBatchMenuItems($this->category);

    Livewire::test(ListMenuItems::class)
        ->assertCanSeeTableRecords($items)
        ->selectTableRecords($items)
        ->callAction(TestAction::make('enableSelectedVouchers')->table()->bulk());

    expect(MenuItem::whereKey($items->modelKeys())->where('is_voucher_enabled', true)->count())->toBe(2)
        ->and(AuditEvent::where('event_type', 'MENU_ITEMS_BULK_VOUCHERS_ENABLED')->exists())->toBeTrue();
});

it('disables vouchers on selected menu items in bulk', function () {
    $items = makeBatchMenuItems($this->category, ['is_voucher_enabled' => true]);

    Livewire::test(ListMenuItems::class)
        ->assertCanSeeTableRecords($items)
        ->selectTableRecords($items)
        ->callAction(TestAction::make('disableSelectedVouchers')->table()->bulk());

    expect(MenuItem::whereKey($items->modelKeys())->where('is_voucher_enabled', false)->count())->toBe(2)
        ->and(AuditEvent::where('event_type', 'MENU_ITEMS_BULK_VOUCHERS_DISABLED')->exists())->toBeTrue();
});
