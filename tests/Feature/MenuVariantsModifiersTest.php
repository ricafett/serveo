<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Domain\Printing\TicketRenderer;
use App\Livewire\Order\OrderEntry;
use App\Models\BillingGroup;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierSet;
use App\Models\ModifierSetItem;
use App\Models\OrderHeader;
use App\Models\OrderItem;
use App\Models\Row;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function cartItemVm(int $menuItemId, int $quantity = 1, ?string $variant = null, ?string $modifier = null): array
{
    $item = ['menu_item_id' => $menuItemId, 'quantity' => $quantity];
    if ($variant !== null) {
        $item['variant_name'] = $variant;
    }
    if ($modifier !== null) {
        $item['modifier_name'] = $modifier;
    }

    return $item;
}

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 2, $this->server
    );

    // Create modifier set
    $this->modSet = ModifierSet::create([
        'display_name' => 'Temperatura',
        'selection_mode' => 'single',
        'is_active' => true,
    ]);
    $this->modFresca = ModifierSetItem::create([
        'modifier_set_id' => $this->modSet->id,
        'display_name' => 'Fresca',
        'is_active' => true,
    ]);
    $this->modNatural = ModifierSetItem::create([
        'modifier_set_id' => $this->modSet->id,
        'display_name' => 'Natural',
        'is_active' => true,
    ]);

    // Create multi-select modifier set
    $this->multiSet = ModifierSet::create([
        'display_name' => 'Extras',
        'selection_mode' => 'multiple',
        'is_active' => true,
    ]);
    ModifierSetItem::create([
        'modifier_set_id' => $this->multiSet->id,
        'display_name' => 'Queijo extra',
        'is_active' => true,
    ]);
    ModifierSetItem::create([
        'modifier_set_id' => $this->multiSet->id,
        'display_name' => 'Bacon extra',
        'is_active' => true,
    ]);

    // Create a menu item with variants
    $this->variantItem = MenuItem::where('display_name', 'Vinho copo')->first();
    $this->variantA = MenuItemVariant::create([
        'menu_item_id' => $this->variantItem->id,
        'display_name' => 'Casa',
        'is_active' => true,
    ]);
    $this->variantB = MenuItemVariant::create([
        'menu_item_id' => $this->variantItem->id,
        'display_name' => 'Reserva',
        'is_active' => true,
    ]);

    // Assign modifier set to this item
    $this->variantItem->update(['modifier_set_id' => $this->modSet->id]);

    // Create a plain item (no variants, no modifiers)
    $this->plainItem = MenuItem::where('display_name', 'Bacalhau')->first();

    // Create a multi-modifier item
    MenuItem::where('display_name', 'Vinho copo')->update(['modifier_set_id' => $this->modSet->id]);
});

// ------------------------------------------------------------------
// Model tests
// ------------------------------------------------------------------

it('menu item reports hasVariants correctly', function () {
    expect($this->variantItem->fresh()->hasVariants())->toBeTrue();
    expect($this->plainItem->fresh()->hasVariants())->toBeFalse();
});

it('menu item reports hasModifiers correctly', function () {
    $item = $this->variantItem->fresh();
    $item->update(['modifier_set_id' => $this->modSet->id]);
    expect($item->fresh()->hasModifiers())->toBeTrue();
    expect($this->plainItem->fresh()->hasModifiers())->toBeFalse();
});

it('activeVariants only returns active variants', function () {
    $this->variantB->update(['is_active' => false]);
    $active = $this->variantItem->fresh()->activeVariants;
    expect($active)->toHaveCount(1)
        ->and($active->first()->display_name)->toBe('Casa');
});

it('modifier set isSingle returns correct value', function () {
    expect($this->modSet->fresh()->isSingle())->toBeTrue();
    expect($this->multiSet->fresh()->isSingle())->toBeFalse();
});

// ------------------------------------------------------------------
// OrderService variant/modifier submission tests
// ------------------------------------------------------------------

it('stores variant and modifier on order item', function () {
    $header = app(OrderService::class)->submit($this->group, $this->server, [
        cartItemVm($this->variantItem->id, 1, 'Casa', 'Fresca'),
    ], $this->zone);

    $item = $header->items->first();
    expect($item->variant_name)->toBe('Casa');
    expect($item->modifier_name)->toBe('Fresca');
});

it('rejects order with missing variant for item that has variants', function () {
    expect(fn () => app(OrderService::class)->submit($this->group, $this->server, [
        cartItemVm($this->variantItem->id, 1, null, null),
    ], $this->zone))->toThrow(RuntimeException::class, 'variant must be selected');
});

it('rejects order with invalid variant name', function () {
    expect(fn () => app(OrderService::class)->submit($this->group, $this->server, [
        cartItemVm($this->variantItem->id, 1, 'InvalidVariant', null),
    ], $this->zone))->toThrow(RuntimeException::class, 'Invalid variant');
});

it('rejects order with invalid modifier name', function () {
    expect(fn () => app(OrderService::class)->submit($this->group, $this->server, [
        cartItemVm($this->variantItem->id, 1, 'Casa', 'InvalidModifier'),
    ], $this->zone))->toThrow(RuntimeException::class, 'Invalid modifier');
});

it('rejects multiple modifiers when selection mode is single', function () {
    expect(fn () => app(OrderService::class)->submit($this->group, $this->server, [
        cartItemVm($this->variantItem->id, 1, 'Casa', 'Fresca, Natural'),
    ], $this->zone))->toThrow(RuntimeException::class, 'Only one modifier');
});

it('allows multiple modifiers when selection mode is multiple', function () {
    // Assign multi-set to the item
    $this->variantItem->update(['modifier_set_id' => $this->multiSet->id]);

    $header = app(OrderService::class)->submit($this->group, $this->server, [
        cartItemVm($this->variantItem->id, 1, 'Casa', 'Queijo extra, Bacon extra'),
    ], $this->zone);

    $item = $header->items->first();
    expect($item->modifier_name)->toBe('Queijo extra, Bacon extra');
});

it('passes when no variant is provided for item without variants', function () {
    $header = app(OrderService::class)->submit($this->group, $this->server, [
        cartItemVm($this->plainItem->id, 1),
    ], $this->zone);

    expect($header->items)->toHaveCount(1);
});

it('variant selection creates distinct order item rows', function () {
    $header = app(OrderService::class)->submit($this->group, $this->server, [
        cartItemVm($this->variantItem->id, 1, 'Casa', null),
        cartItemVm($this->variantItem->id, 2, 'Reserva', null),
    ], $this->zone);

    expect($header->items)->toHaveCount(2);

    $casaItem = $header->items->firstWhere('variant_name', 'Casa');
    $reservaItem = $header->items->firstWhere('variant_name', 'Reserva');
    expect($casaItem)->not->toBeNull();
    expect($reservaItem)->not->toBeNull();
    expect($casaItem->quantity)->toBe(1);
    expect($reservaItem->quantity)->toBe(2);
});

// ------------------------------------------------------------------
// Ticket rendering tests
// ------------------------------------------------------------------

it('production ticket includes variant and modifier on line', function () {
    $header = app(OrderService::class)->submit($this->group, $this->server, [
        cartItemVm($this->variantItem->id, 2, 'Casa', 'Fresca'),
    ], $this->zone);

    $ticket = $this->group->productionTickets()->first();
    expect($ticket)->not->toBeNull();

    $renderer = app(TicketRenderer::class);
    $text = $renderer->renderProductionTicket($ticket);

    expect($text)->toContain('Casa');
    expect($text)->toContain('Fresca');
});

it('bill includes variant and modifier on line', function () {
    $this->group->update(['is_closed' => false]);
    $cashier = makeUser('CASHIER');
    $billPrinter = \App\Models\Printer::where('is_active', true)->first();
    \App\Models\CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $cashier->id, 'printer_id' => $billPrinter->id],
        ['is_active' => true]
    );

    // Submit order
    app(OrderService::class)->submit($this->group, $this->server, [
        cartItemVm($this->variantItem->id, 1, 'Casa', 'Fresca'),
    ], $this->zone);

    $bill = app(\App\Domain\Billing\BillingService::class)->generateInternalBill($this->group, $cashier);

    $renderer = app(TicketRenderer::class);
    $text = $renderer->renderBill($bill);

    expect($text)->toContain('Casa');
    expect($text)->toContain('Fresca');
});

// ------------------------------------------------------------------
// Modifier set deletion nullifies FK
// ------------------------------------------------------------------

it('deleting modifier set nullifies menu item modifier_set_id', function () {
    $this->variantItem->update(['modifier_set_id' => $this->modSet->id]);
    expect($this->variantItem->fresh()->modifier_set_id)->not->toBeNull();

    $this->modSet->delete();

    expect($this->variantItem->fresh()->modifier_set_id)->toBeNull();
});

// ------------------------------------------------------------------
// Livewire OrderEntry tests
// ------------------------------------------------------------------

it('menuItemsData includes variant and modifier info', function () {
    $this->actingAs($this->server);

    $component = Livewire::test(OrderEntry::class, ['billingGroupId' => $this->group->id]);

    $items = $component->get('menuItemsData');
    $variantItem = collect($items)->firstWhere('id', $this->variantItem->id);

    expect($variantItem)->not->toBeNull();
    expect($variantItem['has_variants'])->toBeTrue();
    expect($variantItem['variants'])->toHaveCount(2);
    expect($variantItem['modifier_set'])->not->toBeNull();
    expect($variantItem['modifier_set']['selection_mode'])->toBe('single');
    expect($variantItem['modifier_set']['items'])->toHaveCount(2);
});

it('menuItemsData has no variants for plain item', function () {
    $this->actingAs($this->server);

    $component = Livewire::test(OrderEntry::class, ['billingGroupId' => $this->group->id]);

    $items = $component->get('menuItemsData');
    $plainItem = collect($items)->firstWhere('id', $this->plainItem->id);

    expect($plainItem['has_variants'])->toBeFalse();
    expect($plainItem['variants'])->toBeEmpty();
    expect($plainItem['modifier_set'])->toBeNull();
});

it('submitOrder accepts variant_name and modifier_name via cart', function () {
    $this->actingAs($this->server);

    Livewire::test(OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('submitOrder', [
            cartItemVm($this->variantItem->id, 1, 'Casa', 'Fresca'),
        ])
        ->assertSet('successMessage', 'Order submitted successfully.');

    $order = OrderHeader::where('billing_group_id', $this->group->id)->first();
    $item = $order->items->first();
    expect($item->variant_name)->toBe('Casa');
    expect($item->modifier_name)->toBe('Fresca');
});

it('submitOrder rejects missing variant via cart', function () {
    $this->actingAs($this->server);

    Livewire::test(OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('submitOrder', [
            cartItemVm($this->variantItem->id, 1, null, null),
        ])
        ->assertSet('errorMessage', fn ($msg) => str_contains($msg, 'variant must be selected'));
});

// ------------------------------------------------------------------
// Inactive variant is not sent in menuItemsData
// ------------------------------------------------------------------

it('menuItemsData excludes inactive variants', function () {
    $this->variantB->update(['is_active' => false]);

    $this->actingAs($this->server);
    $component = Livewire::test(OrderEntry::class, ['billingGroupId' => $this->group->id]);

    $items = $component->get('menuItemsData');
    $variantItem = collect($items)->firstWhere('id', $this->variantItem->id);

    expect($variantItem['variants'])->toHaveCount(1);
    expect($variantItem['variants'][0]['display_name'])->toBe('Casa');
});

// ------------------------------------------------------------------
// Inactive modifier set item excluded from menuItemsData
// ------------------------------------------------------------------

it('menuItemsData excludes inactive modifier items', function () {
    $this->modNatural->update(['is_active' => false]);

    $this->actingAs($this->server);
    $component = Livewire::test(OrderEntry::class, ['billingGroupId' => $this->group->id]);

    $items = $component->get('menuItemsData');
    $variantItem = collect($items)->firstWhere('id', $this->variantItem->id);

    expect($variantItem['modifier_set']['items'])->toHaveCount(1);
    expect($variantItem['modifier_set']['items'][0]['display_name'])->toBe('Fresca');
});

// ------------------------------------------------------------------
// Distinct order item rows by modifier
// ------------------------------------------------------------------

it('modifier selection creates distinct order item rows', function () {
    $header = app(OrderService::class)->submit($this->group, $this->server, [
        cartItemVm($this->variantItem->id, 1, 'Casa', 'Fresca'),
        cartItemVm($this->variantItem->id, 2, 'Casa', 'Natural'),
    ], $this->zone);

    expect($header->items)->toHaveCount(2);

    $frescaItem = $header->items->firstWhere('modifier_name', 'Fresca');
    $naturalItem = $header->items->firstWhere('modifier_name', 'Natural');

    expect($frescaItem)->not->toBeNull()
        ->and($frescaItem->quantity)->toBe(1)
        ->and($naturalItem)->not->toBeNull()
        ->and($naturalItem->quantity)->toBe(2);
});

// ------------------------------------------------------------------
// Billing group detail display
// ------------------------------------------------------------------

it('billing group detail shows variant and modifier on order items', function () {
    $this->group->update(['is_closed' => false]);

    app(OrderService::class)->submit($this->group, $this->server, [
        cartItemVm($this->variantItem->id, 2, 'Casa', 'Fresca'),
    ], $this->zone);

    $response = $this->actingAs($this->server)->get("/billing-groups/{$this->group->id}");
    $response->assertOk();
    $response->assertSee($this->variantItem->display_name);
    $response->assertSee('Casa');
    $response->assertSee('Fresca');
});

// ------------------------------------------------------------------
// Assume default / is_default tests
// ------------------------------------------------------------------

it('modifierSetItem is_default enforces at most one default per set', function () {
    $this->modFresca->update(['is_default' => true]);

    // Fresca should now be the only default in this set
    expect($this->modFresca->fresh()->is_default)->toBeTrue();
    expect($this->modNatural->fresh()->is_default)->toBeFalse();

    // Setting Natural as default should clear Fresca
    $this->modNatural->update(['is_default' => true]);

    expect($this->modFresca->fresh()->is_default)->toBeFalse();
    expect($this->modNatural->fresh()->is_default)->toBeTrue();
});

it('modifierSetItem unsetting is_default leaves no default', function () {
    $this->modFresca->update(['is_default' => true]);

    $this->modFresca->update(['is_default' => false]);

    expect($this->modFresca->fresh()->is_default)->toBeFalse();
    expect($this->modNatural->fresh()->is_default)->toBeFalse();
    expect(
        ModifierSetItem::where('modifier_set_id', $this->modSet->id)
            ->where('is_default', true)
            ->count()
    )->toBe(0);
});

it('modifierSet stores and reads assume_default', function () {
    expect($this->modSet->fresh()->assume_default)->toBeFalse();

    $this->modSet->update(['assume_default' => true]);

    expect($this->modSet->fresh()->assume_default)->toBeTrue();
});

it('menuItemsData includes assume_default and is_default', function () {
    $this->modSet->update(['assume_default' => true]);
    $this->modFresca->update(['is_default' => true]);

    $this->actingAs($this->server);
    $component = Livewire::test(OrderEntry::class, ['billingGroupId' => $this->group->id]);

    $items = $component->get('menuItemsData');
    $variantItem = collect($items)->firstWhere('id', $this->variantItem->id);

    expect($variantItem['modifier_set'])->not->toBeNull();
    expect($variantItem['modifier_set']['assume_default'])->toBeTrue();

    // Fresca should be marked as default, Natural should not
    $fresca = collect($variantItem['modifier_set']['items'])->firstWhere('display_name', 'Fresca');
    $natural = collect($variantItem['modifier_set']['items'])->firstWhere('display_name', 'Natural');

    expect($fresca['is_default'])->toBeTrue();
    expect($natural['is_default'])->toBeFalse();
});

it('menuItemsData has assume_default false by default', function () {
    $this->actingAs($this->server);
    $component = Livewire::test(OrderEntry::class, ['billingGroupId' => $this->group->id]);

    $items = $component->get('menuItemsData');
    $variantItem = collect($items)->firstWhere('id', $this->variantItem->id);

    expect($variantItem['modifier_set']['assume_default'])->toBeFalse();

    $allItems = $variantItem['modifier_set']['items'];
    foreach ($allItems as $item) {
        expect($item['is_default'])->toBeFalse();
    }
});
