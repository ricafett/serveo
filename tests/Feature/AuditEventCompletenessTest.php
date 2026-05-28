<?php

use App\Domain\Audit\Audit;
use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\AuditEvent;
use App\Models\BillingStatus;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuItem;
use App\Models\Printer;
use App\Models\Row;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server = makeUser('SERVER');
    $this->cashier = makeUser('CASHIER');
    $this->admin = makeUser('ADMIN');

    // Assign bill printer to cashier (required — no fallback).
    $billPrinter = Printer::where('is_active', true)->first();
    CashierPrinterAssignment::firstOrCreate(
        ['user_id' => $this->cashier->id, 'printer_id' => $billPrinter->id],
        ['is_active' => true]
    );
});

/* ──────────────────────────────────────────────────────────────────
 * Billing group lifecycle
 * ────────────────────────────────────────────────────────────────── */

it('creates audit event on billing group open', function () {
    Auth::login($this->server);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    $event = AuditEvent::where('event_type', 'BILLING_GROUP_OPENED')
        ->where('billing_group_id', $group->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->server->id)
        ->and($event->service_session_id)->toBe($this->session->id);
});

it('creates audit event on billing group status change', function () {
    Auth::login($this->cashier);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(BillingGroupService::class)->setStatus($group, BillingStatus::CLOSED, $this->cashier);

    $event = AuditEvent::where('event_type', 'BILLING_GROUP_STATUS_CHANGED')
        ->where('billing_group_id', $group->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->cashier->id);
});

it('creates audit event on billing group close', function () {
    Auth::login($this->cashier);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(BillingGroupService::class)->close($group, $this->cashier);

    $event = AuditEvent::where('event_type', 'BILLING_GROUP_CLOSED')
        ->where('billing_group_id', $group->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->cashier->id);
});

it('creates audit event on billing group reopen', function () {
    Auth::login($this->cashier);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    app(BillingGroupService::class)->close($group, $this->cashier);
    app(BillingGroupService::class)->reopen($group, $this->cashier);

    $event = AuditEvent::where('event_type', 'BILLING_GROUP_REOPENED')
        ->where('billing_group_id', $group->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->cashier->id);
});

/* ──────────────────────────────────────────────────────────────────
 * Occupancy
 * ────────────────────────────────────────────────────────────────── */

it('creates audit event on occupied zone open', function () {
    Auth::login($this->server);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $zone = app(OccupancyService::class)->assignZone($group, Row::first(), 1, 3, $this->server);

    $event = AuditEvent::where('event_type', 'OCCUPIED_ZONE_OPENED')
        ->where('occupied_zone_id', $zone->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->server->id)
        ->and($event->billing_group_id)->toBe($group->id);
});

it('creates audit event on occupied zone release', function () {
    Auth::login($this->server);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $zone = app(OccupancyService::class)->assignZone($group, Row::first(), 1, 3, $this->server);
    app(OccupancyService::class)->releaseZone($zone, $this->server);

    $event = AuditEvent::where('event_type', 'OCCUPIED_ZONE_RELEASED')
        ->where('occupied_zone_id', $zone->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->server->id);
});

/* ──────────────────────────────────────────────────────────────────
 * Orders
 * ────────────────────────────────────────────────────────────────── */

it('creates audit event on order submission', function () {
    Auth::login($this->server);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    $header = app(OrderService::class)->submit($group, $this->server,
        [['menu_item_id' => $item->id, 'quantity' => 1]]);

    $event = AuditEvent::where('event_type', 'ORDER_SUBMITTED')
        ->where('order_header_id', $header->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->server->id)
        ->and($event->billing_group_id)->toBe($group->id);
});

it('creates audit event on production ticket queued', function () {
    Auth::login($this->server);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $item = MenuItem::where('display_name', 'Bacalhau')->first();

    $header = app(OrderService::class)->submit($group, $this->server,
        [['menu_item_id' => $item->id, 'quantity' => 1]]);

    $event = AuditEvent::where('event_type', 'PRODUCTION_TICKET_QUEUED')
        ->where('order_header_id', $header->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->server->id);
});

it('creates audit event on order item void', function () {
    Auth::login($this->admin);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $item = MenuItem::where('display_name', 'Bacalhau')->first();
    $header = app(OrderService::class)->submit($group, $this->server,
        [['menu_item_id' => $item->id, 'quantity' => 1]]);

    $orderItem = $header->items->first();
    app(OrderService::class)->voidItem($orderItem, $this->admin, 'Test void');

    $event = AuditEvent::where('event_type', 'ORDER_ITEM_VOIDED')
        ->where('order_item_id', $orderItem->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->admin->id);
});

/* ──────────────────────────────────────────────────────────────────
 * Billing
 * ────────────────────────────────────────────────────────────────── */

it('creates audit event on bill generation', function () {
    Auth::login($this->cashier);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $bill = app(BillingService::class)->generateInternalBill($group, $this->cashier);

    $event = AuditEvent::where('event_type', 'BILL_GENERATED')
        ->where('billing_document_id', $bill->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->cashier->id)
        ->and($event->billing_group_id)->toBe($group->id);
});

it('creates audit event on bill reprint', function () {
    Auth::login($this->cashier);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $bill = app(BillingService::class)->generateInternalBill($group, $this->cashier);
    $reprint = app(BillingService::class)->reprintBill($bill, $this->cashier);

    $event = AuditEvent::where('event_type', 'BILL_REPRINTED')
        ->where('billing_document_id', $reprint->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->cashier->id);
});

it('creates audit event on partial payment', function () {
    Auth::login($this->cashier);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $payment = app(BillingService::class)->recordPayment($group, $this->cashier, 10.00, 'Cash');

    $event = AuditEvent::where('event_type', 'PAYMENT_RECORDED')
        ->where('payment_record_id', $payment->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->cashier->id);
});

it('creates audit event on payment void', function () {
    Auth::login($this->cashier);

    $group = app(BillingGroupService::class)->open($this->session, $this->server);
    $payment = app(BillingService::class)->recordPayment($group, $this->cashier, 10.00, 'Cash');
    app(BillingService::class)->voidPayment($payment, $this->cashier);

    $event = AuditEvent::where('event_type', 'PAYMENT_VOIDED')
        ->where('payment_record_id', $payment->id)
        ->first();

    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($this->cashier->id);
});

/* ──────────────────────────────────────────────────────────────────
 * Canonical type coverage
 * ────────────────────────────────────────────────────────────────── */

it('uses only canonical event types defined in Audit::TYPES', function () {
    // Trigger at least one event so the table is not empty.
    Auth::login($this->server);
    $group = app(BillingGroupService::class)->open($this->session, $this->server);

    $used = AuditEvent::pluck('event_type')->unique()->sort()->values()->all();

    expect($used)->not->toBeEmpty();

    foreach ($used as $type) {
        expect(in_array($type, Audit::TYPES, true))->toBeTrue("Event type {$type} is not canonical");
    }
});
