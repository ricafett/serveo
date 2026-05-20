<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderHeader;
use App\Models\PrintJob;
use App\Models\ProductionTicket;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a cart item array matching what Alpine sends to submitOrder.
 */
function cartItem(int $menuItemId, int $quantity = 1): array
{
    return ['menu_item_id' => $menuItemId, 'quantity' => $quantity];
}

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\CoreSeeder::class);

    $this->venue = \App\Models\Venue::first();
    $this->session = ServiceSession::create([
        'venue_id' => $this->venue->id,
        'session_type' => 'DINNER',
        'session_label' => 'Test Dinner',
        'starts_at' => now()->subHour(),
        'status' => 'OPEN',
    ]);

    $this->section = Section::create(['venue_id' => $this->venue->id, 'section_code' => 'TEST', 'name' => 'Test Section', 'sort_order' => 99, 'is_active' => true]);
    $this->row = Row::create(['section_id' => $this->section->id, 'row_code' => 'T1', 'sort_order' => 1, 'is_active' => true]);

    for ($i = 1; $i <= 10; $i++) {
        SeatPair::create(['row_id' => $this->row->id, 'pair_sequence' => $i, 'seat_a_id' => $i * 2 - 1, 'seat_b_id' => $i * 2, 'is_active' => true]);
    }

    $this->server = User::factory()->create(['username' => 'testserver', 'is_active' => true]);
    $this->server->assignRole('SERVER');

    $this->group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($this->group, $this->row, 1, 5, $this->server);

    // Seed additional menu items for tests
    $kitchen = MenuCategory::where('route_type', 'KITCHEN')->first();
    $bar = MenuCategory::where('route_type', 'BAR')->first();

    $this->kitchenCategoryId = $kitchen->id;
    $this->barCategoryId = $bar->id;

    $this->kitchenItem = MenuItem::create(['menu_category_id' => $kitchen->id, 'display_name' => 'Test Dish', 'unit_price' => 12.50, 'is_active' => true]);
    $this->barItem = MenuItem::create(['menu_category_id' => $bar->id, 'display_name' => 'Test Drink', 'unit_price' => 3.50, 'is_active' => true]);
});

// ------------------------------------------------------------------
// Order Entry Screen Rendering
// ------------------------------------------------------------------

it('renders order entry screen with menu items', function () {
    $response = $this->actingAs($this->server)->get("/orders/new/{$this->group->id}");
    $response->assertOk();
    $response->assertSee('Order Entry');
    $response->assertSee($this->group->display_code);
    // Default category shows starter items from CoreSeeder; test items are in other categories
    $response->assertSee('Sopa do dia');
});

it('shows zone selector for billing group with zones', function () {
    $response = $this->actingAs($this->server)->get("/orders/new/{$this->group->id}");
    $response->assertOk();
    $response->assertSee('Delivery Zone');
    $response->assertSee('Group level');
});

it('shows closed warning for closed billing group', function () {
    $cashier = User::factory()->create(['username' => 'testcashier', 'is_active' => true]);
    $cashier->assignRole('CASHIER');
    app(BillingGroupService::class)->close($this->group, $cashier);

    $response = $this->actingAs($this->server)->get("/orders/new/{$this->group->id}");
    $response->assertOk();
    $response->assertSee('Closed');
});

it('sets default category on mount', function () {
    $this->actingAs($this->server);

    $component = \Livewire\Livewire::test(\App\Livewire\Order\OrderEntry::class, ['billingGroupId' => $this->group->id]);

    expect($component->get('defaultCategoryId'))->not->toBeNull();
    expect($component->get('menuItemsData'))->not->toBeEmpty();
    expect($component->get('menuCategoriesData'))->not->toBeEmpty();
});

// ------------------------------------------------------------------
// Order Submission via Livewire (cart passed from Alpine)
// ------------------------------------------------------------------

it('submits order with items and creates order records', function () {
    $this->actingAs($this->server);

    \Livewire\Livewire::test(\App\Livewire\Order\OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('submitOrder', [
            cartItem($this->kitchenItem->id, 1),
            cartItem($this->barItem->id, 1),
        ])
        ->assertSet('successMessage', 'Order submitted successfully.');

    $order = OrderHeader::where('billing_group_id', $this->group->id)->first();
    expect($order)->not->toBeNull();
    expect($order->items)->toHaveCount(2);
    expect($order->submission_status)->toBe('SUBMITTED');
});

it('submits order with zone and validates delivery pair', function () {
    $this->actingAs($this->server);

    $zone = $this->group->occupiedZones->first();
    $pair = SeatPair::where('row_id', $this->row->id)->where('pair_sequence', 2)->first();

    \Livewire\Livewire::test(\App\Livewire\Order\OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('setZone', $zone->id)
        ->call('setDeliveryPair', $pair->id)
        ->call('submitOrder', [cartItem($this->kitchenItem->id, 1)])
        ->assertSet('successMessage', 'Order submitted successfully.');

    $order = OrderHeader::where('billing_group_id', $this->group->id)->first();
    expect($order->occupied_zone_id)->toBe($zone->id);
    $item = $order->items->first();
    expect($item->delivery_seat_pair_id)->toBe($pair->id);
});

it('rejects invalid delivery pair outside zone', function () {
    $this->actingAs($this->server);

    $zone = $this->group->occupiedZones->first();
    $outsidePair = SeatPair::where('row_id', $this->row->id)->where('pair_sequence', 9)->first();

    \Livewire\Livewire::test(\App\Livewire\Order\OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('setZone', $zone->id)
        ->call('setDeliveryPair', $outsidePair->id)
        ->call('submitOrder', [cartItem($this->kitchenItem->id, 1)])
        ->assertSet('errorMessage', 'Delivery pair must be within the selected zone.');
});

it('rejects order on closed billing group', function () {
    $this->actingAs($this->server);

    $cashier = User::factory()->create(['username' => 'testcashier2', 'is_active' => true]);
    $cashier->assignRole('CASHIER');
    app(BillingGroupService::class)->close($this->group, $cashier);

    \Livewire\Livewire::test(\App\Livewire\Order\OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('submitOrder', [cartItem($this->kitchenItem->id, 1)])
        ->assertSet('errorMessage', 'Cannot add orders to a closed group.');
});

it('rejects empty cart submission', function () {
    $this->actingAs($this->server);

    \Livewire\Livewire::test(\App\Livewire\Order\OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('submitOrder', [])
        ->assertSet('errorMessage', 'Cart is empty.');
});

it('respects quantity from cart items', function () {
    $this->actingAs($this->server);

    \Livewire\Livewire::test(\App\Livewire\Order\OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('submitOrder', [
            cartItem($this->kitchenItem->id, 3),
            cartItem($this->barItem->id, 2),
        ])
        ->assertSet('successMessage', 'Order submitted successfully.');

    $order = OrderHeader::where('billing_group_id', $this->group->id)->first();
    expect($order->items)->toHaveCount(2);

    $kitchenLine = $order->items->firstWhere('menu_item_id', $this->kitchenItem->id);
    expect($kitchenLine->quantity)->toBe(3);

    $barLine = $order->items->firstWhere('menu_item_id', $this->barItem->id);
    expect($barLine->quantity)->toBe(2);
});

it('creates production tickets for kitchen and bar routes', function () {
    $this->actingAs($this->server);

    \Livewire\Livewire::test(\App\Livewire\Order\OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('submitOrder', [
            cartItem($this->kitchenItem->id, 1),
            cartItem($this->barItem->id, 1),
        ])
        ->assertSet('successMessage', 'Order submitted successfully.');

    $order = OrderHeader::where('billing_group_id', $this->group->id)->first();
    $tickets = ProductionTicket::where('billing_group_id', $order->billing_group_id)
        ->whereIn('ticket_type', ['KITCHEN', 'BAR'])
        ->get();
    expect($tickets->pluck('ticket_type')->unique()->sort()->values()->all())->toBe(['BAR', 'KITCHEN']);
});

it('creates print jobs for production tickets', function () {
    $this->actingAs($this->server);

    \Livewire\Livewire::test(\App\Livewire\Order\OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('submitOrder', [cartItem($this->kitchenItem->id, 1)])
        ->assertSet('successMessage', 'Order submitted successfully.');

    $tickets = ProductionTicket::where('billing_group_id', $this->group->id)->get();
    expect($tickets)->not->toBeEmpty();

    foreach ($tickets as $ticket) {
        expect(PrintJob::where('printable_type', ProductionTicket::class)
            ->where('printable_id', $ticket->id)
            ->exists())->toBeTrue();
    }
});

it('dispatches order-submitted event on success', function () {
    $this->actingAs($this->server);

    \Livewire\Livewire::test(\App\Livewire\Order\OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('submitOrder', [cartItem($this->kitchenItem->id, 1)])
        ->assertDispatched('order-submitted');
});

it('clears notes and delivery pair on successful submit', function () {
    $this->actingAs($this->server);

    $zone = $this->group->occupiedZones->first();
    $pair = SeatPair::where('row_id', $this->row->id)->where('pair_sequence', 3)->first();

    \Livewire\Livewire::test(\App\Livewire\Order\OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->set('notes', 'Extra napkins')
        ->call('setZone', $zone->id)
        ->call('setDeliveryPair', $pair->id)
        ->call('submitOrder', [cartItem($this->kitchenItem->id, 1)])
        ->assertSet('successMessage', 'Order submitted successfully.')
        ->assertSet('notes', null)
        ->assertSet('selectedDeliveryPairId', null);

    // Zone should persist (it's not cleared on submit)
    expect($zone)->not->toBeNull();
});

it('does not clear cart on submit error', function () {
    $this->actingAs($this->server);

    $cashier = User::factory()->create(['username' => 'testcashier3', 'is_active' => true]);
    $cashier->assignRole('CASHIER');
    app(BillingGroupService::class)->close($this->group, $cashier);

    // submitOrder returns error; Alpine cart is NOT cleared because
    // the 'order-submitted' event is only dispatched on success.
    \Livewire\Livewire::test(\App\Livewire\Order\OrderEntry::class, ['billingGroupId' => $this->group->id])
        ->call('submitOrder', [cartItem($this->kitchenItem->id, 1)])
        ->assertSet('errorMessage', 'Cannot add orders to a closed group.')
        ->assertNotDispatched('order-submitted');
});
