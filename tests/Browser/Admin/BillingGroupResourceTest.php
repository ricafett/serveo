<?php

use App\Domain\Floor\OccupancyService;
use App\Models\Row;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;

test('admin can see billing groups table with checkboxes and set server action', function () {
    $session = $this->scenario();
    $admin = makeUser('ADMIN');
    $server = makeUser('SERVER', 'test-server');

    // Create a billing group with a zone so the table has data
    $row = Row::first();
    $group = createBillingGroup($session, $server);
    app(OccupancyService::class)->assignZone($group, $row, 1, 3, $server);

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit('/admin/billing-groups')
            ->waitForLocation('/admin/billing-groups', 15);

        // Table should render with column headers
        $browser->waitForText('Code', 10);
        $browser->assertSee('Status');
        $browser->assertSee('Session');
        $browser->assertSee('Cover Count');
        $browser->assertSee('Zones');
        $browser->assertSee('Servers');

        // Row checkboxes exist for bulk selection
        $checkboxes = $browser->driver->findElements(
            WebDriverBy::cssSelector('input[type="checkbox"]')
        );
        expect(count($checkboxes))->toBeGreaterThan(0);

        // "Assign Server" bulk action label is rendered
        $source = $browser->driver->getPageSource();
        expect($source)->toContain('Assign Server');
    });
});

test('admin can open set server modal from billing groups bulk actions', function () {
    $session = $this->scenario();
    $admin = makeUser('ADMIN');
    $server = makeUser('SERVER', 'test-server-2');

    // Create a billing group with a zone so the table has data
    $row = Row::first();
    $group = createBillingGroup($session, $server);
    app(OccupancyService::class)->assignZone($group, $row, 1, 3, $server);

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit('/admin/billing-groups')
            ->waitForLocation('/admin/billing-groups', 15)
            ->waitForText('Code', 10);

        // Select first row checkbox
        $checkboxes = $browser->driver->findElements(
            WebDriverBy::cssSelector('input[type="checkbox"]')
        );
        expect(count($checkboxes))->toBeGreaterThan(0);
        $checkboxes[0]->click();
        $browser->pause(800);

        // Open bulk actions dropdown
        $browser->waitForText('Bulk actions', 5)
            ->press('Bulk actions')
            ->pause(500);

        // Click "Assign Server"
        $browser->waitForText('Assign Server', 5)
            ->press('Assign Server')
            ->pause(1000);

        // Modal appears with server select
        $browser->waitForText('Server', 10);
        $browser->screenshot('billing-group-set-server-modal');
    });
});
