<?php

use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CoreSeeder::class);
});

// ------------------------------------------------------------------
// Authentication & Landing
// ------------------------------------------------------------------

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/floor');
    $response->assertRedirect('/login');
});

it('shows login page for guests', function () {
    $response = $this->get('/login');
    $response->assertOk();
    $response->assertSee('Sign In');
});

it('redirects authenticated users from login to home', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/login');
    $response->assertRedirect('/home');
});

it('shows dashboard for server on home', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/home');
    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('Floor');
});

it('shows dashboard for cashier on home', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASHIER');

    $response = $this->actingAs($user)->get('/home');
    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('Billing Groups');
});

it('shows dashboard for admin on home', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('ADMIN');

    $response = $this->actingAs($user)->get('/home');
    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('Admin Panel');
});

// ------------------------------------------------------------------
// Role-based route access
// ------------------------------------------------------------------

it('allows server to access floor', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/floor');
    $response->assertOk();
    $response->assertSee('Floor');
});

it('allows admin to access floor', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('ADMIN');

    $response = $this->actingAs($user)->get('/floor');
    $response->assertOk();
});

it('denies cashier from floor', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASHIER');

    $response = $this->actingAs($user)->get('/floor');
    $response->assertForbidden();
});

it('allows cashier to access lookup', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASHIER');

    $response = $this->actingAs($user)->get('/lookup');
    $response->assertOk();
    $response->assertSee('Billing Groups');
});

it('allows admin to access lookup', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('ADMIN');

    $response = $this->actingAs($user)->get('/lookup');
    $response->assertOk();
});

it('denies server from lookup', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/lookup');
    $response->assertForbidden();
});

it('allows server to access billing group detail', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/billing-groups/1');
    $response->assertOk();
    $response->assertSee('G-001');
});

it('allows cashier to access checkout', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASHIER');

    $response = $this->actingAs($user)->get('/checkout/1');
    $response->assertOk();
    $response->assertSee('Checkout');
});

it('allows cashier to access reprint panel via group route', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASHIER');

    $response = $this->actingAs($user)->get('/reprint/1');
    $response->assertOk();
    $response->assertSee('Reprint & Documents');
});

// ------------------------------------------------------------------
// Layout & UI
// ------------------------------------------------------------------

it('renders operational layout with navigation for server', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/floor');
    $response->assertOk();
    $response->assertSee('Floor');
    // Bottom/sidebar navigation should be present on all operational pages
    $response->assertSee('Dashboard');
});

it('renders operational layout with navigation for cashier', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASHIER');

    $response = $this->actingAs($user)->get('/lookup');
    $response->assertOk();
    $response->assertSee('Checkout');
});

it('includes language switcher in layout', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    // Header (with language switcher) is only on the dashboard
    $response = $this->actingAs($user)->get('/home');
    $response->assertOk();
    $response->assertSee('language-switcher', false);
    $response->assertSee('EN'); // active locale shown in dropdown trigger
});

it('includes theme toggle in layout', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    // Header (with theme toggle) is only on the dashboard
    $response = $this->actingAs($user)->get('/home');
    $response->assertOk();
    $response->assertSee('theme-toggle', false);
    $response->assertSee('Light');
    $response->assertSee('Dark');
});

// ------------------------------------------------------------------
// Logout
// ------------------------------------------------------------------

it('logs out user and redirects to login', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->post('/logout');
    $response->assertRedirect('/login');
    $this->assertGuest();
});

// ------------------------------------------------------------------
// Web login form
// ------------------------------------------------------------------

it('logs in user via web form with valid credentials', function () {
    $user = User::factory()->create([
        'username' => 'testserver1',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);
    $user->assignRole('SERVER');

    $response = $this->post('/login', [
        'username' => 'testserver1',
        'password' => 'password',
    ]);

    $response->assertRedirect('/home');
    $this->assertAuthenticatedAs($user);
});

it('rejects login with invalid credentials', function () {
    $user = User::factory()->create([
        'username' => 'testserver2',
        'password' => bcrypt('password'),
        'is_active' => true,
    ]);

    $response = $this->post('/login', [
        'username' => 'testserver2',
        'password' => 'wrongpassword',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertGuest();
});

it('rejects login for inactive user', function () {
    $user = User::factory()->create([
        'username' => 'inactive',
        'password' => bcrypt('password'),
        'is_active' => false,
    ]);

    $response = $this->post('/login', [
        'username' => 'inactive',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('username');
    $this->assertGuest();
});
