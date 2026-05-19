<?php

use App\Models\ServiceSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $this->seed(\Database\Seeders\CoreSeeder::class);
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
    $response->assertDontSee('Reprint');
    $response->assertDontSee('Admin Panel');
});

it('shows lookup and reprint tiles for cashier', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASHIER');

    $response = $this->actingAs($user)->get('/home');
    $response->assertSee('Billing Groups');
    $response->assertSee('Search and manage billing groups');
    $response->assertSee('Reprint');
    $response->assertSee('Reprint bills and documents');
    $response->assertDontSee('Floor');
    $response->assertDontSee('Admin Panel');
});

it('shows all tiles for admin', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('ADMIN');

    $response = $this->actingAs($user)->get('/home');
    $response->assertSee('Floor');
    $response->assertSee('Billing Groups');
    $response->assertSee('Reprint');
    $response->assertSee('Admin Panel');
    $response->assertSee('Configuration and system settings');
});

// ------------------------------------------------------------------
// Active session display
// ------------------------------------------------------------------

it('shows active session name when one exists', function () {
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
    $response->assertSee($session->name);
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
