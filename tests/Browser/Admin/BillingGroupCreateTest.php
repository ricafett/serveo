<?php

use App\Models\BillingStatus;
use App\Models\ServiceSession;
use Laravel\Dusk\Browser;

test('admin can create a billing group via filament', function () {
    $this->scenario();
    $admin = makeUser('ADMIN');
    $activeStatusId = BillingStatus::where('code', BillingStatus::ACTIVE)->value('id');
    $openSessionId = ServiceSession::where('status', 'OPEN')->latest('starts_at')->value('id');

    $this->browse(function (Browser $browser) use ($admin, $activeStatusId, $openSessionId) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit('/admin/billing-groups/create')
            ->waitForText('Name', 10);

        $browser->type('input[id="form.name"]', 'Dusk Test Group')
            ->select('select[id="form.billing_status_id"]', (string) $activeStatusId)
            ->select('select[id="form.service_session_id"]', (string) $openSessionId)
            ->pause(500);

        $browser->press('Create')
            ->pause(2000);

        $browser->waitForText('Dusk Test Group', 10);
        $browser->assertSee('Dusk Test Group');
    });
});

test('admin can create billing group with cover count', function () {
    $this->scenario();
    $admin = makeUser('ADMIN');
    $activeStatusId = BillingStatus::where('code', BillingStatus::ACTIVE)->value('id');
    $openSessionId = ServiceSession::where('status', 'OPEN')->latest('starts_at')->value('id');

    $this->browse(function (Browser $browser) use ($admin, $activeStatusId, $openSessionId) {
        $browser->driver->manage()->deleteAllCookies();

        $browser->visit('/admin/login')
            ->waitForText('Sign in', 5)
            ->type('input[id="form.email"]', $admin->email)
            ->type('input[id="form.password"]', 'secret')
            ->press('Sign in')
            ->waitForLocation('/admin', 30);

        $browser->visit('/admin/billing-groups/create')
            ->waitForText('Name', 10);

        $browser->type('input[id="form.name"]', 'Cover Count Group')
            ->type('input[id="form.cover_count"]', '6')
            ->select('select[id="form.billing_status_id"]', (string) $activeStatusId)
            ->select('select[id="form.service_session_id"]', (string) $openSessionId)
            ->pause(500);

        $browser->press('Create')
            ->pause(2000);

        $browser->waitForText('Cover Count Group', 10);
        $browser->assertSee('Cover Count Group');
        $browser->assertSee('6');
    });
});

test('admin create page shows validation error without name', function () {
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

        $browser->visit('/admin/billing-groups/create')
            ->waitForText('Name', 10);

        $browser->press('Create')
            ->pause(1000);

        $browser->assertPathIs('/admin/billing-groups/create');
    });
});
