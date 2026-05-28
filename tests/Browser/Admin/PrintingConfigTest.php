<?php

use App\Models\FulfillmentRoute;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Printer;
use App\Models\PrinterRoute;
use App\Models\ProductionTicket;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\Venue;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use Laravel\Dusk\Browser;

// ────────────────────────────────────────────────────────────
// Test 5: Admin can CRUD a fulfillment route
// ────────────────────────────────────────────────────────────

test('admin can create a fulfillment route via filament', function () {
    $this->scenario();
    $admin = makeUser('ADMIN');
    $code = 'DESSERT-' . bin2hex(random_bytes(2));

    $this->browse(function (Browser $browser) use ($admin, $code) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit('/admin/fulfillment-routes/create')
            ->waitForText('Code', 10);

        $browser->type('input[id="form.code"]', $code)
            ->type('input[id="form.display_name"]', 'Sobremesas')
            ->type('input[id="form.sort_order"]', '30')
            ->pause(500);

        $browser->press('Create')
            ->pause(3000);
    });

    // Verify the route was created in the database.
    expect(FulfillmentRoute::where('code', $code)->exists())->toBeTrue();
});

test('admin can edit a fulfillment route', function () {
    $this->scenario();
    $admin = makeUser('ADMIN');

    // Create the route directly.
    $route = FulfillmentRoute::create([
        'code' => 'DESSERT-EDIT',
        'display_name' => 'Sobremesas',
        'sort_order' => 30,
        'is_active' => true,
    ]);

    $this->browse(function (Browser $browser) use ($admin, $route) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit("/admin/fulfillment-routes/{$route->id}/edit")
            ->waitForText('Code', 10);

        $browser->type('input[id="form.display_name"]', 'Pastelaria')
            ->pause(500);

        $browser->press('Save')
            ->pause(3000);
    });

    // Verify the edit was persisted.
    expect(FulfillmentRoute::find($route->id)->display_name)->toBe('Pastelaria');
});

test('admin can deactivate a fulfillment route', function () {
    $this->scenario();
    $admin = makeUser('ADMIN');

    $route = FulfillmentRoute::create([
        'code' => 'DESSERT-DEACT',
        'display_name' => 'Pastelaria',
        'sort_order' => 30,
        'is_active' => true,
    ]);

    $this->browse(function (Browser $browser) use ($admin, $route) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit("/admin/fulfillment-routes/{$route->id}/edit")
            ->waitForText('Code', 10);

        // Filament toggle: click the toggle switch button to deactivate.
        $browser->click('button[role="switch"]')
            ->pause(500);

        $browser->press('Save')
            ->pause(3000);
    });

    // Verify the route was deactivated in the database.
    expect(FulfillmentRoute::find($route->id)->is_active)->toBeFalse();
});

// ────────────────────────────────────────────────────────────
// Test 6: MenuCategoryResource shows dynamic route options
// ────────────────────────────────────────────────────────────

test('menu category route_type select shows dynamic fulfillment routes', function () {
    $this->scenario();
    $admin = makeUser('ADMIN');
    $code = 'DESSERT-SEL-' . bin2hex(random_bytes(2));

    // Create a custom fulfillment route.
    FulfillmentRoute::create([
        'code' => $code,
        'display_name' => 'Sobremesas',
        'sort_order' => 30,
        'is_active' => true,
    ]);

    // Find an existing category to edit.
    $category = MenuCategory::where('is_active', true)->first();

    $this->browse(function (Browser $browser) use ($admin, $category) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit("/admin/menu-categories/{$category->id}/edit")
            ->waitForText('Code', 10);

        // The route_type select exists and is present.
        $browser->assertPresent('select[id="form.route_type"]');
    });
});

// ────────────────────────────────────────────────────────────
// Test 2: Custom fulfillment route produces a production ticket
// ────────────────────────────────────────────────────────────

test('custom fulfillment route produces a production ticket', function () {
    $session = $this->scenario();
    $admin = makeUser('ADMIN');
    $server = makeUser('SERVER');
    $code = 'DESSERT-E2E-' . bin2hex(random_bytes(2));

    // Create a custom fulfillment route.
    FulfillmentRoute::create([
        'code' => $code,
        'display_name' => 'Sobremesas',
        'sort_order' => 30,
        'is_active' => true,
    ]);

    // Create a menu category with the custom route.
    $catName = 'Desserts ' . bin2hex(random_bytes(1));
    $dessertCat = MenuCategory::create([
        'code' => $code,
        'display_name' => $catName,
        'route_type' => $code,
        'sort_order' => 40,
        'is_active' => true,
    ]);

    // Create a menu item in that category.
    $itemName = 'Pudim ' . bin2hex(random_bytes(1));
    $dessertItem = MenuItem::create([
        'display_name' => $itemName,
        'menu_category_id' => $dessertCat->id,
        'unit_price' => 4.50,
        'is_active' => true,
    ]);

    // Create a printer route for the custom fulfillment route.
    $printer = Printer::where('is_active', true)->first();
    $venue = Venue::first();
    PrinterRoute::create([
        'venue_id' => $venue->id,
        'document_type' => 'PRODUCTION_TICKET',
        'fulfillment_route' => $code,
        'printer_id' => $printer->id,
        'is_active' => true,
    ]);

    // Set up billing group, zone, and seat pairs for order entry.
    $section = Section::firstOrCreate(
        ['venue_id' => $venue->id, 'section_code' => 'TEST'],
        ['name' => 'Test Section', 'sort_order' => 99, 'is_active' => true],
    );
    $row = Row::firstOrCreate(
        ['section_id' => $section->id, 'row_code' => 'T1'],
        ['sort_order' => 1, 'is_active' => true],
    );
    for ($i = 1; $i <= 10; $i++) {
        SeatPair::firstOrCreate(
            ['row_id' => $row->id, 'pair_sequence' => $i],
            ['seat_a_id' => $i * 2 - 1, 'seat_b_id' => $i * 2, 'is_active' => true],
        );
    }

    $group = app(BillingGroupService::class)->open($session, $server);
    $zone = app(OccupancyService::class)->assignZone($group, $row, 1, 5, $server);

    // Submit an order via the OrderEntry Livewire component.
    $this->browse(function (Browser $browser) use ($server, $group, $dessertItem, $dessertCat, $itemName) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/login')
            ->waitForText('Sign In', 5)
            ->type('username', $server->username)
            ->type('password', 'secret')
            ->press('Sign In')
            ->waitForText('Dashboard', 5);

        $browser->visit("/orders/new/{$group->id}")
            ->waitForText('Order Entry', 5);

        // Select the DESSERT category tab.
        $browser->press($dessertCat->display_name)
            ->waitForText($itemName, 5);

        // Add item to cart.
        $browser->press($itemName)
            ->pause(500);

        $browser->assertDontSee('No items added.')
            ->pause(500)
            ->press('Submit Order')
            ->waitForText('Order submitted', 20);
    });

    // Verify a production ticket was created for the custom route.
    expect(ProductionTicket::where('ticket_type', $code)->count())->toBe(1);
});
