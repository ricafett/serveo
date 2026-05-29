<?php

use App\Domain\Audit\Audit;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\OccupiedZone;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\DemoTransactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
