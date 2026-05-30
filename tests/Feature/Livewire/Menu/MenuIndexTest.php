<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierSet;
use App\Models\ModifierSetItem;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\DemoTransactionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CoreSeeder::class);
    $this->seed(DemoTransactionSeeder::class);
});

// ------------------------------------------------------------------
// Role Access
// ------------------------------------------------------------------

it('allows SERVER to access menu page', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/menu');
    $response->assertOk();
    $response->assertSee('Menu');
});

it('allows CASHIER to access menu page', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('CASHIER');

    $response = $this->actingAs($user)->get('/menu');
    $response->assertOk();
    $response->assertSee('Menu');
});

it('allows ADMIN to access menu page', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('ADMIN');

    $response = $this->actingAs($user)->get('/menu');
    $response->assertOk();
    $response->assertSee('Menu');
});

it('redirects guest from menu page', function () {
    $response = $this->get('/menu');
    $response->assertRedirect('/login');
});

// ------------------------------------------------------------------
// Catalog Rendering
// ------------------------------------------------------------------

it('lists active menu categories on menu page', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $category = MenuCategory::where('is_active', true)->first();
    expect($category)->not->toBeNull();

    $response = $this->actingAs($user)->get('/menu');
    $response->assertOk();
    $response->assertSee($category->display_name);
});

it('lists active menu items with prices', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $item = MenuItem::where('is_active', true)->first();
    expect($item)->not->toBeNull();

    $response = $this->actingAs($user)->get('/menu');
    $response->assertOk();
    $response->assertSee($item->display_name);
    $response->assertSee(number_format($item->unit_price, 2));
});

it('does not render order submission actions on menu page', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/menu');
    $response->assertOk();
    $response->assertDontSee('Submit Order');
    $response->assertDontSee('Add to order');
});

// ------------------------------------------------------------------
// Dark theme
// ------------------------------------------------------------------

it('renders menu page with dark theme classes', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('SERVER');

    $response = $this->actingAs($user)->get('/menu');
    $response->assertOk();
    // The layout includes dark: variants
    $response->assertSee('dark:bg-gray-900');
});
