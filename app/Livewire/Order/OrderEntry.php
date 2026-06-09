<?php

namespace App\Livewire\Order;

use App\Domain\Orders\OrderService;
use App\Models\BillingGroup;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OccupiedZone;
use App\Models\SeatPair;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class OrderEntry extends Component
{
    public int $billingGroupId;

    public ?int $selectedZoneId = null;

    public ?int $selectedDeliveryPairId = null;

    public ?string $notes = null;

    /** @var array<int, array{id: int, display_name: string, unit_price: float, category_id: int, route_type: string, has_variants: bool, variants: array, modifier_set: array|null}> */
    public array $menuItemsData = [];

    /** @var array<int, array{id: int, display_name: string}> */
    public array $menuCategoriesData = [];

    public ?int $defaultCategoryId = null;

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    /** Idempotency key to prevent duplicate order submissions. */
    public string $idempotencyKey;

    public bool $isSubmitting = false;

    public function mount(int $billingGroupId): void
    {
        $this->billingGroupId = $billingGroupId;
        $this->idempotencyKey = (string) Str::uuid();

        $this->defaultCategoryId = MenuCategory::where('is_active', true)->orderBy('sort_order')->value('id');

        $this->menuCategoriesData = MenuCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (MenuCategory $c) => [
                'id' => $c->id,
                'display_name' => $c->display_name,
            ])
            ->all();

        $this->menuItemsData = MenuItem::with(['category', 'activeVariants', 'modifierSet.defaultItem', 'modifierSet.items' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get()
            ->map(fn (MenuItem $item) => [
                'id' => $item->id,
                'display_name' => $item->display_name,
                'unit_price' => (float) $item->unit_price,
                'category_id' => $item->menu_category_id,
                'route_type' => $item->category?->route_type ?? 'NONE',
                'has_variants' => $item->activeVariants->isNotEmpty(),
                'variants' => $item->activeVariants->map(fn ($v) => [
                    'id' => $v->id,
                    'display_name' => $v->display_name,
                ])->values()->all(),
                'modifier_set' => $item->modifierSet ? [
                    'id' => $item->modifierSet->id,
                    'display_name' => $item->modifierSet->display_name,
                    'selection_mode' => $item->modifierSet->selection_mode,
                    'assume_default' => (bool) $item->modifierSet->assume_default,
                    'default_modifier_display_name' => $item->modifierSet->defaultItem?->display_name,
                    'items' => $item->modifierSet->items->map(fn ($mi) => [
                        'id' => $mi->id,
                        'display_name' => $mi->display_name,
                    ])->values()->all(),
                ] : null,
            ])
            ->all();
    }

    public function getGroupProperty(): ?BillingGroup
    {
        return BillingGroup::with([
            'occupiedZones' => fn ($q) => $q->where('is_open', true)->with('row.section', 'row.seatPairs'),
            'status',
        ])->find($this->billingGroupId);
    }

    public function getZonesProperty()
    {
        return $this->group?->occupiedZones ?? collect();
    }

    public function getSelectedZoneProperty(): ?OccupiedZone
    {
        if (! $this->selectedZoneId) {
            return null;
        }

        return $this->zones->firstWhere('id', $this->selectedZoneId);
    }

    public function getSelectedDeliveryPairProperty(): ?SeatPair
    {
        if (! $this->selectedDeliveryPairId) {
            return null;
        }

        return SeatPair::find($this->selectedDeliveryPairId);
    }

    public function setZone(?int $zoneId): void
    {
        $this->selectedZoneId = $zoneId;
        $this->selectedDeliveryPairId = null;
        $this->errorMessage = null;
    }

    public function setDeliveryPair(?int $pairId): void
    {
        $this->selectedDeliveryPairId = $pairId;
    }

    public function submitOrder(array $cart = []): void
    {
        // Prevent concurrent submissions from the same component instance.
        if ($this->isSubmitting) {
            return;
        }

        $this->errorMessage = null;
        $this->successMessage = null;

        $group = $this->group;
        if (! $group) {
            $this->errorMessage = __('Billing group not found.');

            return;
        }

        if ($group->is_closed) {
            $this->errorMessage = __('Cannot add orders to a closed group.');

            return;
        }

        if (! $group->serviceSession?->isOpen()) {
            $this->errorMessage = __('No open service session.');

            return;
        }

        if (empty($cart)) {
            $this->errorMessage = __('Cart is empty.');

            return;
        }

        $zone = $this->selectedZone;
        if ($zone && $zone->billing_group_id !== $group->id) {
            $this->errorMessage = __('Invalid zone for this billing group.');

            return;
        }

        // Validate delivery pair if provided
        if ($this->selectedDeliveryPairId && $zone) {
            $pair = SeatPair::find($this->selectedDeliveryPairId);
            if (! $pair || $pair->row_id !== $zone->row_id
                || $pair->pair_sequence < $zone->start_seat_pair_sequence
                || $pair->pair_sequence > $zone->end_seat_pair_sequence) {
                $this->errorMessage = __('Delivery pair must be within the selected zone.');

                return;
            }
        }

        try {
            $this->isSubmitting = true;

            $lines = collect($cart)->map(fn ($item) => [
                'menu_item_id' => $item['menu_item_id'],
                'quantity' => $item['quantity'],
                'delivery_seat_pair_id' => $this->selectedDeliveryPairId,
                'variant_name' => $item['variant_name'] ?? null,
                'modifier_name' => $item['modifier_name'] ?? null,
                'note' => $item['note'] ?? null,
            ])->all();

            app(OrderService::class)->submit(
                $group,
                Auth::user(),
                $lines,
                $zone,
                $this->notes,
                $this->idempotencyKey,
            );

            $this->successMessage = __('Order submitted successfully.');
            $this->notes = null;
            $this->selectedDeliveryPairId = null;
            $this->idempotencyKey = (string) Str::uuid();

            $this->dispatch('order-submitted');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function render()
    {
        return view('livewire.order.order-entry')
            ->layout('layouts.operational');
    }
}
