<?php

use App\Domain\Audit\Audit;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Filament\Resources\BillingGroupResource\Pages\ViewBillingGroup;
use App\Filament\Resources\BillingGroupResource\RelationManagers\BillingDocumentsRelationManager;
use App\Models\BillingDocument;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OccupiedZone;
use App\Models\OrderHeader;
use App\Models\OrderItem;
use App\Models\PaymentRecord;
use App\Models\Printer;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\DemoTransactionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CoreSeeder::class);
    $this->seed(DemoTransactionSeeder::class);

    $this->venue = Venue::first();
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

    $this->admin = User::factory()->create(['username' => 'testadmin', 'is_active' => true]);
    $this->admin->assignRole('ADMIN');

    $this->billPrinter = Printer::where('is_active', true)->first();
    CashierPrinterAssignment::create([
        'user_id' => $this->admin->id,
        'printer_id' => $this->billPrinter->id,
        'is_active' => true,
    ]);
});

it('admin can access billing groups resource', function () {
    $response = $this->actingAs($this->admin)->get('/admin/billing-groups');
    $response->assertOk();
});

it('non-admin cannot access billing groups resource', function () {
    $response = $this->actingAs($this->server)->get('/admin/billing-groups');
    $response->assertForbidden();
});

it('assigns server to all occupied zones of billing groups', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($group, $this->row, 1, 5, $this->server);

    $newServer = User::factory()->create(['username' => 'newserver', 'is_active' => true]);
    $newServer->assignRole('SERVER');

    // Before: zones have the original server
    expect(OccupiedZone::where('billing_group_id', $group->id)->first()->server_id)
        ->toBe($this->server->id);

    // Simulate the bulk action: update all zones of the group
    OccupiedZone::where('billing_group_id', $group->id)
        ->update(['server_id' => $newServer->id]);

    // After: all zones have the new server
    $zones = OccupiedZone::where('billing_group_id', $group->id)->get();
    expect($zones)->not->toBeEmpty();
    foreach ($zones as $zone) {
        expect($zone->server_id)->toBe($newServer->id);
    }
});

it('records audit event when assigning server to billing group zones', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($group, $this->row, 1, 5, $this->server);

    $newServer = User::factory()->create(['username' => 'auditserver', 'is_active' => true]);
    $newServer->assignRole('SERVER');

    Audit::record(
        'server_assigned',
        "Server ID {$newServer->id} assigned to all zones of billing group {$group->display_code}",
        ['server_id' => $newServer->id, 'billing_group_id' => $group->id],
        ['service_session_id' => $group->service_session_id],
    );

    $this->assertDatabaseHas('audit_events', [
        'event_type' => 'server_assigned',
        'service_session_id' => $group->service_session_id,
    ]);
});

it('bulk assign updates multiple billing groups zones', function () {
    $group1 = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($group1, $this->row, 1, 3, $this->server);

    $group2 = app(BillingGroupService::class)->open($this->session, $this->server);
    app(OccupancyService::class)->assignZone($group2, $this->row, 5, 7, $this->server);

    $newServer = User::factory()->create(['username' => 'bulkserver', 'is_active' => true]);
    $newServer->assignRole('SERVER');

    $zoneIds = OccupiedZone::whereIn('billing_group_id', [$group1->id, $group2->id])->pluck('id');
    OccupiedZone::whereIn('id', $zoneIds)->update(['server_id' => $newServer->id]);

    // Both groups' zones updated
    $zones1 = OccupiedZone::where('billing_group_id', $group1->id)->get();
    $zones2 = OccupiedZone::where('billing_group_id', $group2->id)->get();

    expect($zones1)->not->toBeEmpty();
    expect($zones2)->not->toBeEmpty();
    foreach ($zones1 as $zone) {
        expect($zone->server_id)->toBe($newServer->id);
    }
    foreach ($zones2 as $zone) {
        expect($zone->server_id)->toBe($newServer->id);
    }
});

it('shows admin billing group financials and order history on the view page', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server, name: 'Admin View Group');
    $zone = app(OccupancyService::class)->assignZone($group, $this->row, 1, 3, $this->server);

    $category = MenuCategory::create([
        'code' => 'ADMIN-VIEW',
        'display_name' => 'Admin View',
        'route_type' => 'KITCHEN',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $item = MenuItem::create([
        'menu_category_id' => $category->id,
        'display_name' => 'Admin View Dish',
        'unit_price' => 12.50,
        'is_active' => true,
        'is_voucher_enabled' => false,
    ]);

    $order = OrderHeader::create([
        'billing_group_id' => $group->id,
        'occupied_zone_id' => $zone->id,
        'ordered_by_user_id' => $this->server->id,
        'ordered_at' => now()->subMinutes(10),
        'submission_status' => 'SUBMITTED',
    ]);

    OrderItem::create([
        'order_header_id' => $order->id,
        'menu_item_id' => $item->id,
        'quantity' => 2,
        'unit_price' => 12.50,
        'line_subtotal' => 25.00,
        'fulfillment_route' => 'KITCHEN',
    ]);

    PaymentRecord::create([
        'billing_group_id' => $group->id,
        'recorded_by_user_id' => $this->admin->id,
        'recorded_at' => now()->subMinutes(5),
        'amount' => 10.00,
        'payment_label' => 'Cash',
        'is_voided' => false,
    ]);

    BillingDocument::create([
        'billing_group_id' => $group->id,
        'printer_id' => $this->billPrinter->id,
        'document_type' => BillingDocument::TYPE_INTERNAL_BILL,
        'document_status' => 'PRINTED',
        'document_number' => 'B-TEST-0001',
        'subtotal_amount' => 25.00,
        'total_amount' => 25.00,
        'requested_at' => now()->subMinutes(4),
        'printed_at' => now()->subMinutes(4),
        'is_reprint' => false,
        'created_by_user_id' => $this->admin->id,
    ]);

    $response = $this->actingAs($this->admin)->get("/admin/billing-groups/{$group->id}/view");

    $response->assertOk()
        ->assertSee('Admin View Group')
        ->assertSee('25.00 €')
        ->assertSee('10.00 €')
        ->assertSee('15.00 €')
        ->assertSee('Admin View Dish')
        ->assertSee('Cash');
});

it('reprints a bill from the admin billing group bills list', function () {
    $group = app(BillingGroupService::class)->open($this->session, $this->server, name: 'Reprint Group');

    $original = BillingDocument::create([
        'billing_group_id' => $group->id,
        'printer_id' => $this->billPrinter->id,
        'document_type' => BillingDocument::TYPE_INTERNAL_BILL,
        'document_status' => 'PRINTED',
        'document_number' => 'B-TEST-0002',
        'subtotal_amount' => 20.00,
        'total_amount' => 20.00,
        'requested_at' => now()->subMinutes(3),
        'printed_at' => now()->subMinutes(3),
        'is_reprint' => false,
        'created_by_user_id' => $this->admin->id,
    ]);

    $this->actingAs($this->admin);

    Livewire::test(BillingDocumentsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => ViewBillingGroup::class,
    ])
        ->assertActionVisible(TestAction::make('reprint')->table($original))
        ->callAction(TestAction::make('reprint')->table($original))
        ->assertNotified();

    $reprints = BillingDocument::where('billing_group_id', $group->id)
        ->where('is_reprint', true)
        ->get();

    expect($reprints)->toHaveCount(1)
        ->and((float) $reprints->first()->total_amount)->toBe(20.00)
        ->and($reprints->first()->reprint_of_billing_document_id)->toBe($original->id);
});
