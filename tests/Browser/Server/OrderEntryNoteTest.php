<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Models\MenuItem;
use App\Models\Row;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\Venue;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->scenario = $this->scenario();
    $this->server = makeUser('SERVER');

    $venue = Venue::first();
    $section = Section::firstOrCreate(
        ['venue_id' => $venue->id, 'section_code' => 'NOTE'],
        ['name' => 'Note Test Section', 'sort_order' => 99, 'is_active' => true],
    );
    $this->row = Row::firstOrCreate(
        ['section_id' => $section->id, 'row_code' => 'N1'],
        ['sort_order' => 1, 'is_active' => true],
    );
    for ($i = 1; $i <= 10; $i++) {
        SeatPair::firstOrCreate(
            ['row_id' => $this->row->id, 'pair_sequence' => $i],
            ['seat_a_id' => $i * 2 - 1, 'seat_b_id' => $i * 2, 'is_active' => true],
        );
    }

    $this->group = app(BillingGroupService::class)->open($this->scenario, $this->server);
    app(OccupancyService::class)->assignZone($this->group, $this->row, 1, 5, $this->server);
});

function loginAndOpenOrderEntry(Browser $browser, int $groupId): Browser
{
    return $browser->visit('/login')
        ->waitForText('Sign In', 10)
        ->type('username', test()->server->username)
        ->type('password', 'secret')
        ->press('Sign In')
        ->waitForText('Dashboard', 10)
        ->visit("/orders/new/{$groupId}")
        ->waitForText('Order Entry', 10);
}

function addItemToCart(Browser $browser, MenuItem $menuItem): Browser
{
    return $browser->press($menuItem->category->display_name)
        ->waitForText($menuItem->display_name, 5)
        ->press($menuItem->display_name)
        ->pause(800);
}

function openNoteModal(Browser $browser): void
{
    $browser->click('@cart-item-menu')
        ->pause(600)
        ->click('@add-note-btn')
        ->pause(1000);
}

test('note modal display toggles via Add note and close', function () {
    $menuItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $this->browse(function (Browser $browser) use ($menuItem) {
        $browser->driver->manage()->deleteAllCookies();
        loginAndOpenOrderEntry($browser, $this->group->id);
        addItemToCart($browser, $menuItem);

        // Verify hidden initially
        $display = $browser->script("return document.getElementById('note-modal').style.display");
        expect($display[0])->toBe('none');

        // Open note modal via dropdown
        openNoteModal($browser);

        // Verify modal is visible
        $display = $browser->script("return document.getElementById('note-modal').style.display");
        expect($display[0])->toBe('flex');

        // Close via save-note dispatch (triggers closeNoteModal)
        $browser->script("document.querySelector('[dusk=\"save-note\"]').click()");
        $browser->pause(600);

        // Verify modal is hidden again
        $display = $browser->script("return document.getElementById('note-modal').style.display");
        expect($display[0])->toBe('none');
    });
});

test('note text is saved to cart item', function () {
    $menuItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $this->browse(function (Browser $browser) use ($menuItem) {
        $browser->driver->manage()->deleteAllCookies();
        loginAndOpenOrderEntry($browser, $this->group->id);
        addItemToCart($browser, $menuItem);

        openNoteModal($browser);

        // Type note via JS to avoid interactability issues
        $browser->script("
            var ta = document.getElementById('note-modal-text');
            if (ta) {
                ta.value = 'no onion, well done';
                ta.dispatchEvent(new Event('input', { bubbles: true }));
            }
        ");
        $browser->pause(300);

        // Click save via JS
        $browser->script("document.querySelector('[dusk=\"save-note\"]').click()");
        $browser->pause(800);

        // Note text should appear in cart
        $browser->assertSee('no onion, well done');
    });
});

test('cancel button hides modal without saving note', function () {
    $menuItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $this->browse(function (Browser $browser) use ($menuItem) {
        $browser->driver->manage()->deleteAllCookies();
        loginAndOpenOrderEntry($browser, $this->group->id);
        addItemToCart($browser, $menuItem);

        openNoteModal($browser);

        // Type note then click Cancel via JS
        $browser->script("
            var ta = document.getElementById('note-modal-text');
            if (ta) ta.value = 'should not save';
        ");
        $browser->pause(200);

        // Find Cancel button and click
        $browser->script("
            var btns = document.querySelectorAll('#note-modal button');
            for (var i = 0; i < btns.length; i++) {
                if (btns[i].textContent.trim() === 'Cancel') {
                    btns[i].click();
                    break;
                }
            }
        ");
        $browser->pause(600);

        // Text NOT saved
        $browser->assertDontSee('should not save');

        // Modal hidden
        $display = $browser->script("return document.getElementById('note-modal').style.display");
        expect($display[0])->toBe('none');
    });
});

test('backdrop click closes note modal', function () {
    $menuItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $this->browse(function (Browser $browser) use ($menuItem) {
        $browser->driver->manage()->deleteAllCookies();
        loginAndOpenOrderEntry($browser, $this->group->id);
        addItemToCart($browser, $menuItem);

        openNoteModal($browser);

        // Click backdrop via JS
        $browser->script("
            var el = document.querySelector('[dusk=\"note-modal-backdrop\"]');
            if (el) el.click();
        ");
        $browser->pause(600);

        $display = $browser->script("return document.getElementById('note-modal').style.display");
        expect($display[0])->toBe('none');
    });
});
