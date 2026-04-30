<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;

use App\Domain\Orders\OrderService;
use App\Models\BillingGroup;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OccupiedZone;
use App\Models\SeatPair;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class OrderEntry extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'filament.pages.order-entry';
    protected static ?string $title = null;

    public static function getNavigationLabel(): string
    {
        return __('order.title');
    }

    public function getTitle(): string
    {
        return __('order.new_order') . ' · ' . $this->group?->display_code;
    }

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'orders/new/{record}';
    }

    public ?int $record = null;
    public ?BillingGroup $group = null;

    /** @var array<int, array{menu_item_id:int, quantity:int}> */
    public array $cart = [];

    public ?int $occupiedZoneId = null;
    public ?int $deliveryPairId = null;
    public ?string $notes = null;

    public function mount(int $record): void
    {
        $this->record = $record;
        $this->group = BillingGroup::with(['occupiedZones.row.seatPairs'])->findOrFail($record);
        if ($this->group->is_closed) {
            abort(403, __('billing.closed_group'));
        }
        $this->occupiedZoneId = $this->group->occupiedZones->where('is_open', true)->first()?->id;
    }

    public function addItem(int $menuItemId): void
    {
        foreach ($this->cart as $i => $line) {
            if ($line['menu_item_id'] === $menuItemId) {
                $this->cart[$i]['quantity']++;
                return;
            }
        }
        $this->cart[] = ['menu_item_id' => $menuItemId, 'quantity' => 1];
    }

    public function removeItem(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function changeQty(int $index, int $delta): void
    {
        if (! isset($this->cart[$index])) return;
        $this->cart[$index]['quantity'] = max(1, $this->cart[$index]['quantity'] + $delta);
    }

    public function submitOrder(): void
    {
        if (! Auth::user()?->can('order.create')) {
            Notification::make()->title(__('order.unauthorized'))->danger()->send();
            return;
        }
        if (empty($this->cart)) {
            Notification::make()->title(__('order.cart_empty_warning'))->warning()->send();
            return;
        }

        $zone = $this->occupiedZoneId ? OccupiedZone::find($this->occupiedZoneId) : null;

        $lines = array_map(function ($l) {
            return [
                'menu_item_id'          => (int) $l['menu_item_id'],
                'quantity'              => (int) $l['quantity'],
                'delivery_seat_pair_id' => $this->deliveryPairId,
            ];
        }, $this->cart);

        try {
            app(OrderService::class)->submit($this->group, Auth::user(), $lines, $zone, $this->notes);
            Notification::make()->title(__('order.order_sent'))->success()->send();
            $this->redirect(BillingGroupDetail::getUrl(['record' => $this->group->id]));
        } catch (\Throwable $e) {
            Notification::make()->title(__('order.order_failed'))->body($e->getMessage())->danger()->send();
        }
    }

    public function getViewData(): array
    {
        $categories = MenuCategory::with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('display_name')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $cartDetailed = [];
        $total = 0.0;
        foreach ($this->cart as $i => $line) {
            $item = MenuItem::find($line['menu_item_id']);
            if (! $item) continue;
            $sub = round((float) $item->unit_price * $line['quantity'], 2);
            $total += $sub;
            $cartDetailed[] = [
                'index'    => $i,
                'name'     => $item->display_name,
                'qty'      => $line['quantity'],
                'price'    => (float) $item->unit_price,
                'subtotal' => $sub,
            ];
        }

        $zoneOptions = $this->group->occupiedZones->where('is_open', true)
            ->mapWithKeys(fn ($z) => [$z->id => $z->row?->section?->section_code.' · '.$z->rangeLabel()])->all();

        $pairOptions = [];
        if ($this->occupiedZoneId) {
            $zone = $this->group->occupiedZones->firstWhere('id', $this->occupiedZoneId);
            if ($zone) {
                foreach ($zone->row->seatPairs as $pair) {
                    if ($pair->pair_sequence >= $zone->start_seat_pair_sequence
                        && $pair->pair_sequence <= $zone->end_seat_pair_sequence) {
                        $pairOptions[$pair->id] = "Pair {$pair->pair_sequence}";
                    }
                }
            }
        }

        return compact('categories', 'cartDetailed', 'total', 'zoneOptions', 'pairOptions');
    }
}
