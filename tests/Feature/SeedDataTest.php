<?php

beforeEach(function () {
    $this->artisan('db:seed');
});

it('creates at least 2 orders with multiple items', function () {
    expect(\App\Models\OrderHeader::count())->toBeGreaterThanOrEqual(2)
        ->and(\App\Models\OrderItem::count())->toBeGreaterThanOrEqual(4);
});

it('creates production tickets linked to order items', function () {
    $tickets = \App\Models\ProductionTicket::with('items')->get();
    expect($tickets)->not->toBeEmpty();

    $linked = $tickets->filter(fn ($t) => $t->items->isNotEmpty());
    expect($linked)->not->toBeEmpty();
});

it('creates at least one internal bill and one payment', function () {
    expect(\App\Models\BillingDocument::where('document_type', 'INTERNAL_BILL')->count())->toBeGreaterThanOrEqual(1)
        ->and(\App\Models\PaymentRecord::count())->toBeGreaterThanOrEqual(1);
});

it('creates audit events for seeded transactions', function () {
    expect(\App\Models\AuditEvent::count())->toBeGreaterThanOrEqual(5);
});

it('creates a second closed billing group', function () {
    $closed = \App\Models\BillingGroup::where('is_closed', true)->first();
    expect($closed)->not->toBeNull()
        ->and($closed->status?->code)->toBe('CLOSED');
});

it('does not break existing tests after seeding', function () {
    // Ensure the seeded data is realistic enough for manual smoke testing
    $group = \App\Models\BillingGroup::where('display_code', 'G-001')->first();
    expect($group)->not->toBeNull()
        ->and($group->orderHeaders)->toHaveCount(3)
        ->and($group->billingDocuments)->toHaveCount(2); // bill + reprint
});
