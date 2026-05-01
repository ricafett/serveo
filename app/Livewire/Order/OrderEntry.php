<?php

namespace App\Livewire\Order;

use App\Domain\Orders\OrderService;
use App\Models\BillingGroup;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OccupiedZone;
use App\Models\SeatPair;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OrderEntry extends Component
{
    public int $billingGroupId;

    public ?int $selectedZoneId = null;
    public ?int $selectedDeliveryPairId = null;
    public ?string $notes = null;
    public ?int $selectedCategoryId = null;

    public array $cart = [];

    public ?string $errorMessage = null;
    public ?string $successMessage = null;

    public function mount(int $billingGroupId): void
    {
        $this->billingGroupId = $billingGroupId;

        $zones = $this->group?->occupiedZones ?? collect();
        if ($zones->count() === 1) {
            $this->selectedZoneId = $zones->first()->id;
        }

        $this->selectedCategoryId = MenuCategory::where('is_active', true)->orderBy('sort_order')->value('id');
    }

    public function getGroupProperty(): ?BillingGroup
    {
        return BillingGroup::with([
            'occupiedZones' => fn ($q) => $q->where('is_open', true)->with('row.section', 'row.seatPairs'),
            'status',
        ])->find($this->billingGroupId);
    }

    public function getMenuCategoriesProperty()
    {
        return MenuCategory::where('is_active', true)->orderBy('sort_order')->get();
    }

    public function getMenuItemsProperty()
    {
        $query = MenuItem::with('category')
            ->where('is_active', true)
            ->orderBy('display_name');

        if ($this->selectedCategoryId) {
            $query->where('menu_category_id', $this->selectedCategoryId);
        }

        return $query->get();
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

    public function getCartTotalProperty(): float
    {
        return collect($this->cart)->sum(fn ($item) => $item['unit_price'] * $item['quantity']);
    }

    public function getCartItemCountProperty(): int
    {
        return collect($this->cart)->sum('quantity');
    }

    public function selectCategory(int $categoryId): void
    {
        $this->selectedCategoryId = $categoryId;
    }

    public function addToCart(int $menuItemId): void
    {
        $existingIndex = collect($this->cart)->search(fn ($item) => $item['menu_item_id'] === $menuItemId);

        if ($existingIndex !== false) {
            $this->cart[$existingIndex]['quantity']++;
        } else {
            $menuItem = MenuItem::with('category')->findOrFail($menuItemId);
            $this->cart[] = [
                'menu_item_id' => $menuItem->id,
                'display_name' => $menuItem->display_name,
                'unit_price' => (float) $menuItem->unit_price,
                'quantity' => 1,
                'route_type' => $menuItem->category?->route_type ?? 'NONE',
            ];
        }

        $this->errorMessage = null;
    }

    public function removeFromCart(int $index): void
    {
        if (isset($this->cart[$index])) {
            unset($this->cart[$index]);
            $this->cart = array_values($this->cart);
        }
    }

    public function incrementCartItem(int $index): void
    {
        if (isset($this->cart[$index])) {
            $this->cart[$index]['quantity']++;
        }
    }

    public function decrementCartItem(int $index): void
    {
        if (isset($this->cart[$index])) {
            if ($this->cart[$index]['quantity'] > 1) {
                $this->cart[$index]['quantity']--;
            } else {
                $this->removeFromCart($index);
            }
        }
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

    public function submitOrder(): void
    {
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

        if (empty($this->cart)) {
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
            $lines = collect($this->cart)->map(fn ($item) => [
                'menu_item_id' => $item['menu_item_id'],
                'quantity' => $item['quantity'],
                'delivery_seat_pair_id' => $this->selectedDeliveryPairId,
            ])->all();

            app(OrderService::class)->submit(
                $group,
                Auth::user(),
                $lines,
                $zone,
                $this->notes,
            );

            $this->successMessage = __('Order submitted successfully.');
            $this->cart = [];
            $this->notes = null;
            $this->selectedDeliveryPairId = null;

            $this->dispatch('order-submitted');
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function goBack(): void
    {
        $this->redirect(route('billing-groups.detail', ['id' => $this->billingGroupId]), navigate: true);
    }

    public function render()
    {
        return view('livewire.order.order-entry')
            ->layout('layouts.operational');
    }
}
