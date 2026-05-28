<?php

use App\Models\ServiceSession;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CoreSeeder::class);
});

// ------------------------------------------------------------------
// Landing behavior
// ------------------------------------------------------------------

it('shows dashboard for authenticated server', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/home');
    $response->assertOk();
    $response->assertSee('Dashboard');
});

it('shows dashboard for authenticated cashier', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASHIER');

    $response = $this->actingAs($user)->get('/home');
    $response->assertOk();
    $response->assertSee('Dashboard');
});

it('shows dashboard for authenticated admin', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('ADMIN');

    $response = $this->actingAs($user)->get('/home');
    $response->assertOk();
    $response->assertSee('Dashboard');
});

// ------------------------------------------------------------------
// Role-based tile visibility
// ------------------------------------------------------------------

it('shows floor tile for server', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/home');
    $response->assertSee('Floor');
    $response->assertSee('View occupancy and manage seating');
    $response->assertDontSee('Billing Groups');
    $response->assertDontSee('Admin Panel');
});

it('hides operational tiles for server when no session is open', function () {
    ServiceSession::query()->delete();

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/home');
    $response->assertSee('No open service session');
    // Tile labels should not appear in the tile grid
    $response->assertDontSee('View occupancy and manage seating');
    $response->assertDontSee('Search and manage billing groups');
});

it('hides operational tiles for cashier when no session is open', function () {
    ServiceSession::query()->delete();

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASHIER');

    $response = $this->actingAs($user)->get('/home');
    $response->assertSee('No open service session');
    $response->assertDontSee('Search and manage billing groups');
});

it('shows admin panel tile even when no session is open', function () {
    ServiceSession::query()->delete();

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('ADMIN');

    $response = $this->actingAs($user)->get('/home');
    $response->assertSee('No open service session');
    $response->assertDontSee('View occupancy and manage seating');
    $response->assertDontSee('Search and manage billing groups');
    $response->assertSee('Configuration and system settings');
});

it('shows lookup tile for cashier', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASHIER');

    $response = $this->actingAs($user)->get('/home');
    $response->assertSee('Billing Groups');
    $response->assertSee('Search and manage billing groups');
    $response->assertDontSee('Floor');
    $response->assertDontSee('Admin Panel');
});

it('shows all tiles for admin', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('ADMIN');

    $response = $this->actingAs($user)->get('/home');
    $response->assertSee('Floor');
    $response->assertSee('Billing Groups');
    $response->assertSee('Admin Panel');
    $response->assertSee('Configuration and system settings');
});

// ------------------------------------------------------------------
// Active session display
// ------------------------------------------------------------------

it('shows active session name when one exists', function () {
    ServiceSession::query()->delete();
    $session = ServiceSession::create([
        'venue_id' => 1,
        'session_label' => 'Test Session',
        'session_type' => 'DINNER',
        'status' => 'OPEN',
        'starts_at' => now(),
    ]);

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/home');
    $response->assertSee('Active session');
    $response->assertSee($session->session_label);
});

it('shows no session warning when none is open', function () {
    ServiceSession::query()->delete();

    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/home');
    $response->assertSee('No open service session');
});

// ------------------------------------------------------------------
// Navigation
// ------------------------------------------------------------------

it('includes home link in operational navigation', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/home');
    $response->assertSee('Dashboard');
});
