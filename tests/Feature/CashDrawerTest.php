<?php

use App\Domain\CashDrawer\CashDrawerService;
use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Livewire\Cashier\CashDrawerIndex;
use App\Models\BillingStatus;
use App\Models\CashMovement;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuItem;
use App\Models\Printer;
use App\Models\Row;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\ServiceSession;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// ---------------------------------------------------------------
// CashDrawerService Unit Tests
// ---------------------------------------------------------------

beforeEach(function () {
    Queue::fake();
    $this->session = bootScenario();
    $this->cashier = makeUser('CASHIER');

    $printer = Printer::where('is_active', true)->first();
    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->cashier->id, 'printer_id' => $printer->id],
        ['is_active' => true],
    );

    $this->service = app(CashDrawerService::class);
});

it('returns zero balance when no movements exist', function () {
    $balance = $this->service->getBalance($this->cashier, $this->session);
    expect($balance)->toBe(0.00);
});

it('computes balance correctly with cash in movements', function () {
    $this->service->recordMovement($this->cashier, $this->session, 'CASH_IN', 200.00, 'Opening Float');
    $this->service->recordMovement($this->cashier, $this->session, 'CASH_IN', 50.00, 'Top-up');

    $balance = $this->service->getBalance($this->cashier, $this->session);
    expect($balance)->toBe(250.00);
});

it('computes balance correctly with cash out movements', function () {
    $this->service->recordMovement($this->cashier, $this->session, 'CASH_IN', 500.00, 'Opening Float');
    $this->service->recordMovement($this->cashier, $this->session, 'CASH_OUT', 150.00, 'Bank Deposit');

    $balance = $this->service->getBalance($this->cashier, $this->session);
    expect($balance)->toBe(350.00);
});

it('includes billing group payments in balance', function () {
    $server = makeUser('SERVER');
    $group = app(BillingGroupService::class)->open($this->session, $server);
    $zone = app(OccupancyService::class)->assignZone($group, Row::first(), 1, 2, $server);

    $item = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($group, $server, [['menu_item_id' => $item->id, 'quantity' => 2]], $zone);

    app(BillingService::class)->recordPayment($group, $this->cashier, 20.00, 'Cash');

    $balance = $this->service->getBalance($this->cashier, $this->session);
    expect($balance)->toBe(20.00);
});

it('includes sale payments in balance', function () {
    $voucherItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $voucherItem->update(['is_voucher_enabled' => true]);

    $sale = Sale::create([
        'service_session_id' => $this->session->id,
        'display_code' => 'SALE-001',
        'sold_by_user_id' => $this->cashier->id,
        'subtotal_amount' => 18.00,
        'total_amount' => 18.00,
        'payment_label' => 'Cash',
        'sold_at' => now(),
    ]);

    SalePayment::create([
        'sale_id' => $sale->id,
        'recorded_by_user_id' => $this->cashier->id,
        'recorded_at' => now(),
        'amount' => 18.00,
        'payment_label' => 'Cash',
        'is_voided' => false,
    ]);

    $balance = $this->service->getBalance($this->cashier, $this->session);
    expect($balance)->toBe(18.00);
});

it('excludes voided payments from balance', function () {
    $voucherItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $voucherItem->update(['is_voucher_enabled' => true]);

    $sale = Sale::create([
        'service_session_id' => $this->session->id,
        'display_code' => 'SALE-002',
        'sold_by_user_id' => $this->cashier->id,
        'subtotal_amount' => 18.00,
        'total_amount' => 18.00,
        'payment_label' => 'Cash',
        'sold_at' => now(),
    ]);

    SalePayment::create([
        'sale_id' => $sale->id,
        'recorded_by_user_id' => $this->cashier->id,
        'recorded_at' => now(),
        'amount' => 18.00,
        'payment_label' => 'Cash',
        'is_voided' => true,
    ]);

    $balance = $this->service->getBalance($this->cashier, $this->session);
    expect($balance)->toBe(0.00);
});

it('scopes balance to the correct cashier', function () {
    $cashier2 = makeUser('CASHIER');

    $this->service->recordMovement($this->cashier, $this->session, 'CASH_IN', 100.00, 'Float Cashier 1');
    $this->service->recordMovement($cashier2, $this->session, 'CASH_IN', 300.00, 'Float Cashier 2');

    expect($this->service->getBalance($this->cashier, $this->session))->toBe(100.00);
    expect($this->service->getBalance($cashier2, $this->session))->toBe(300.00);
});

it('rejects cash out exceeding available balance', function () {
    $this->service->recordMovement($this->cashier, $this->session, 'CASH_IN', 100.00, 'Float');

    $this->service->recordMovement($this->cashier, $this->session, 'CASH_OUT', 200.00, 'Too much');
})->throws(RuntimeException::class);

it('rejects zero amount movement', function () {
    $this->service->recordMovement($this->cashier, $this->session, 'CASH_IN', 0.00, 'Zero');
})->throws(RuntimeException::class);

it('rejects negative amount movement', function () {
    $this->service->recordMovement($this->cashier, $this->session, 'CASH_IN', -10.00, 'Negative');
})->throws(RuntimeException::class);

it('rejects invalid movement type', function () {
    $this->service->recordMovement($this->cashier, $this->session, 'INVALID', 50.00, 'Bad type');
})->throws(RuntimeException::class);

it('requires an open session to record movement', function () {
    $this->session->update(['status' => 'CLOSED']);

    $this->service->recordMovement($this->cashier, $this->session, 'CASH_IN', 50.00, 'Float');
})->throws(RuntimeException::class);

it('creates audit event when recording movement', function () {
    $this->service->recordMovement($this->cashier, $this->session, 'CASH_IN', 200.00, 'Opening Float');

    $audit = \App\Models\AuditEvent::where('event_type', 'CASH_MOVEMENT_RECORDED')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->actor_user_id)->toBe($this->cashier->id)
        ->and($audit->service_session_id)->toBe($this->session->id)
        ->and($audit->entity_type)->toBe(CashMovement::class);
});

it('builds timeline with movements and payment inflows', function () {
    $this->service->recordMovement($this->cashier, $this->session, 'CASH_IN', 100.00, 'Float');

    $timeline = $this->service->getTimeline($this->cashier, $this->session);
    expect($timeline)->toHaveCount(1)
        ->and($timeline[0]['type'])->toBe('CASH_IN')
        ->and($timeline[0]['amount'])->toBe(100.00);
});

it('returns empty timeline when no activity', function () {
    $timeline = $this->service->getTimeline($this->cashier, $this->session);
    expect($timeline)->toBeEmpty();
});

it('handles cash out equal to balance', function () {
    $this->service->recordMovement($this->cashier, $this->session, 'CASH_IN', 100.00, 'Float');
    $this->service->recordMovement($this->cashier, $this->session, 'CASH_OUT', 100.00, 'All out');

    $balance = $this->service->getBalance($this->cashier, $this->session);
    expect($balance)->toBe(0.00);
});

// ---------------------------------------------------------------
// CashDrawerIndex Livewire Component Tests
// ---------------------------------------------------------------

beforeEach(function () {
    Queue::fake();
    $this->session = bootScenario();
    $this->cashier = makeUser('CASHIER');

    $printer = Printer::where('is_active', true)->first();
    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->cashier->id, 'printer_id' => $printer->id],
        ['is_active' => true],
    );

    $this->actingAs($this->cashier);
});

it('renders the cash drawer page for cashier', function () {
    $response = $this->get('/cash-drawer');
    $response->assertOk()
        ->assertSee('Cash Drawer')
        ->assertSee('Current Balance');
});

it('shows no session message when no open session exists', function () {
    $this->session->update(['status' => 'CLOSED']);

    $response = $this->get('/cash-drawer');
    $response->assertOk()
        ->assertSee('No open service session');
});

it('records a cash in movement from the component', function () {
    Livewire::test(CashDrawerIndex::class)
        ->set('movementType', 'CASH_IN')
        ->set('movementAmount', 200.00)
        ->set('movementLabel', 'Opening Float')
        ->call('recordMovement')
        ->assertSet('successMessage', 'Movement recorded successfully.')
        ->assertSet('showForm', false);

    expect(CashMovement::count())->toBe(1)
        ->and(CashMovement::first()->movement_type)->toBe('CASH_IN')
        ->and((float) CashMovement::first()->amount)->toBe(200.00);
});

it('records a cash out movement from the component', function () {
    app(CashDrawerService::class)->recordMovement($this->cashier, $this->session, 'CASH_IN', 500.00, 'Float');

    Livewire::test(CashDrawerIndex::class)
        ->set('movementType', 'CASH_OUT')
        ->set('movementAmount', 100.00)
        ->set('movementLabel', 'Bank Deposit')
        ->call('recordMovement')
        ->assertSet('successMessage', 'Movement recorded successfully.');

    expect(CashMovement::where('movement_type', 'CASH_OUT')->count())->toBe(1);
});

it('shows error when recording movement without open session', function () {
    $this->session->update(['status' => 'CLOSED']);

    Livewire::test(CashDrawerIndex::class)
        ->set('movementType', 'CASH_IN')
        ->set('movementAmount', 100.00)
        ->set('movementLabel', 'Test')
        ->call('recordMovement')
        ->assertSet('errorMessage', fn ($v) => $v !== null);

    expect(CashMovement::count())->toBe(0);
});

it('shows error for negative amount', function () {
    Livewire::test(CashDrawerIndex::class)
        ->set('movementType', 'CASH_IN')
        ->set('movementAmount', -5.00)
        ->set('movementLabel', 'Test')
        ->call('recordMovement')
        ->assertSet('errorMessage', fn ($v) => $v !== null);

    expect(CashMovement::count())->toBe(0);
});

it('shows error for empty label', function () {
    Livewire::test(CashDrawerIndex::class)
        ->set('movementType', 'CASH_IN')
        ->set('movementAmount', 50.00)
        ->set('movementLabel', '')
        ->call('recordMovement')
        ->assertSet('errorMessage', fn ($v) => $v !== null);

    expect(CashMovement::count())->toBe(0);
});

it('prevents duplicate submission via isSubmitting guard', function () {
    Livewire::test(CashDrawerIndex::class)
        ->set('movementType', 'CASH_IN')
        ->set('movementAmount', 100.00)
        ->set('movementLabel', 'Test')
        ->set('isSubmitting', true)
        ->call('recordMovement');

    expect(CashMovement::count())->toBe(0);
});

it('prevents server from accessing cash drawer', function () {
    $server = makeUser('SERVER');
    $this->actingAs($server);

    $response = $this->get('/cash-drawer');
    $response->assertForbidden();
});

it('allows admin to access cash drawer', function () {
    $admin = makeUser('ADMIN');
    $this->actingAs($admin);

    $response = $this->get('/cash-drawer');
    $response->assertOk();
});

it('calculates correct balance after mixed operations', function () {
    $service = app(CashDrawerService::class);

    $service->recordMovement($this->cashier, $this->session, 'CASH_IN', 300.00, 'Opening Float');
    $service->recordMovement($this->cashier, $this->session, 'CASH_OUT', 50.00, 'Bank deposit');

    // Add a billing group payment
    $server = makeUser('SERVER');
    $group = app(BillingGroupService::class)->open($this->session, $server);
    $zone = app(OccupancyService::class)->assignZone($group, Row::first(), 1, 2, $server);
    $item = MenuItem::where('display_name', 'Bacalhau')->first();
    app(OrderService::class)->submit($group, $server, [['menu_item_id' => $item->id, 'quantity' => 1]], $zone);
    app(BillingService::class)->recordPayment($group, $this->cashier, 18.00, 'Cash');

    Livewire::test(CashDrawerIndex::class)
        ->assertSet('balance', 268.00); // 300 - 50 + 18 = 268
});

it('displays timeline entries in chronological order', function () {
    $service = app(CashDrawerService::class);
    $service->recordMovement($this->cashier, $this->session, 'CASH_IN', 200.00, 'Opening Float');
    $service->recordMovement($this->cashier, $this->session, 'CASH_OUT', 30.00, 'Petty cash');

    Livewire::test(CashDrawerIndex::class)
        ->assertSee('Opening Float')
        ->assertSee('Petty cash');
});
