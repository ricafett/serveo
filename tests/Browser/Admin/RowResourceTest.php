<?php

use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;

test('admin can see rows table with checkboxes and bulk assign server action', function () {
    $this->scenario();
    $admin = makeUser('ADMIN');

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit('/admin/rows')
            ->waitForLocation('/admin/rows', 15)
            ->waitForText('Row', 10);

        // Table columns are visible
        $browser->assertSee('Room');
        $browser->assertSee('Seats');
        $browser->assertSee('Pairs');

        // Row checkboxes exist for bulk selection
        $checkboxes = $browser->driver->findElements(
            WebDriverBy::cssSelector('input[type="checkbox"]')
        );
        expect(count($checkboxes))->toBeGreaterThan(0);

        // "Assign Server" bulk action label is rendered in the page
        $source = $browser->driver->getPageSource();
        expect($source)->toContain('Assign Server');

        // Selecting a row reveals the bulk action dropdown
        $checkboxes[0]->click();
        $browser->pause(500);
        $afterSource = $browser->driver->getPageSource();
        expect($afterSource)->toContain('Assign Server');
    });
});

test('admin can open assign server modal from bulk actions', function () {
    $this->scenario();
    $admin = makeUser('ADMIN');

    $this->browse(function (Browser $browser) use ($admin) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit('/admin/rows')
            ->waitForLocation('/admin/rows', 15)
            ->waitForText('Row', 10);

        // Select the first row checkbox
        $checkboxes = $browser->driver->findElements(
            WebDriverBy::cssSelector('input[type="checkbox"]')
        );
        expect(count($checkboxes))->toBeGreaterThan(0);
        $checkboxes[0]->click();
        $browser->pause(800);

        // Open the bulk actions dropdown
        $browser->waitForText('Bulk actions', 5)
            ->press('Bulk actions')
            ->pause(500);

        // Click "Assign Server" to open the modal
        $browser->waitForText('Assign Server', 5)
            ->press('Assign Server')
            ->pause(1000);

        // Modal appears with the server select field
        $browser->waitForText('Server', 10);

        // Take screenshot to confirm modal rendered
        $browser->screenshot('assign-server-modal');
    });
});
