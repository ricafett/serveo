<?php

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Orders\OrderService;
use App\Models\MenuItem;
use App\Models\Row;
use App\Models\SeatPair;

beforeEach(function () {
    $this->session = bootScenario();
    $this->server  = makeUser('SERVER');
    $this->group   = app(BillingGroupService::class)->open($this->session, $this->server);
    $this->zone    = app(OccupancyService::class)->assignZone(
        $this->group, Row::first(), 1, 4, $this->server
    );
});

it('accepts a valid delivery seat-pair override', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $validPair = SeatPair::where('row_id', $this->zone->row_id)
        ->where('pair_sequence', 2)
        ->first();

    $header = app(OrderService::class)->submit($this->group, $this->server, [
        [
            'menu_item_id' => $kitchenItem->id,
            'quantity' => 1,
            'delivery_seat_pair_id' => $validPair->id,
        ],
    ], $this->zone);

    $item = $header->items->first();
    expect($item->delivery_seat_pair_id)->toBe($validPair->id)
        ->and($item->delivery_reference_label)->toBe("Pair {$validPair->pair_sequence}");
});

it('rejects override outside zone range', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();
    $invalidPair = SeatPair::where('row_id', $this->zone->row_id)
        ->where('pair_sequence', 5)
        ->first();

    expect(fn () => app(OrderService::class)->submit($this->group, $this->server, [
        [
            'menu_item_id' => $kitchenItem->id,
            'quantity' => 1,
            'delivery_seat_pair_id' => $invalidPair->id,
        ],
    ], $this->zone))->toThrow(RuntimeException::class, 'Delivery pair must fall inside the occupied zone range');
});

it('rejects override in different row', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    // Create a second row with a seat pair
    $section = \App\Models\Section::first();
    $secondRow = \App\Models\Row::firstOrCreate(
        ['section_id' => $section->id, 'row_code' => '2'],
        ['sort_order' => 2, 'is_active' => true],
    );
    $seatA = \App\Models\Seat::firstOrCreate(['row_id' => $secondRow->id, 'seat_number' => 1], ['sort_order' => 1, 'is_active' => true]);
    $seatB = \App\Models\Seat::firstOrCreate(['row_id' => $secondRow->id, 'seat_number' => 2], ['sort_order' => 2, 'is_active' => true]);
    $otherPair = \App\Models\SeatPair::firstOrCreate(
        ['row_id' => $secondRow->id, 'pair_sequence' => 1],
        ['seat_a_id' => $seatA->id, 'seat_b_id' => $seatB->id, 'is_active' => true],
    );

    expect(fn () => app(OrderService::class)->submit($this->group, $this->server, [
        [
            'menu_item_id' => $kitchenItem->id,
            'quantity' => 1,
            'delivery_seat_pair_id' => $otherPair->id,
        ],
    ], $this->zone))->toThrow(RuntimeException::class, 'Delivery pair must be in the same row as the occupied zone');
});

it('assigns default center label when no override provided', function () {
    $kitchenItem = MenuItem::where('display_name', 'Bacalhau')->first();

    $header = app(OrderService::class)->submit($this->group, $this->server, [
        ['menu_item_id' => $kitchenItem->id, 'quantity' => 1],
    ], $this->zone);

    $item = $header->items->first();
    expect($item->delivery_reference_label)->toBe($this->zone->defaultDeliveryLabel());
});
