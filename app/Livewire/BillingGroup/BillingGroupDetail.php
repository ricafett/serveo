<?php

namespace App\Livewire\BillingGroup;

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Floor\ZoneOverlapException;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
use App\Models\Row;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BillingGroupDetail extends Component
{
    public int $id;
    public ?BillingGroup $group = null;

    // Add zone modal
    public bool $showAddZoneModal = false;
    public ?int $zoneRowId = null;
    public ?int $zoneStartSeq = null;
    public ?int $zoneEndSeq = null;
    public ?string $deliveryLabel = null;
    public ?string $errorMessage = null;

    // Status change
    public ?string $newStatusCode = null;

    public function mount(int $id): void
    {
        $this->id = $id;
        $this->loadGroup();
    }

    public function loadGroup(): void
    {
        $this->group = BillingGroup::with([
            'status',
            'occupiedZones.row.section',
            'occupiedZones.server',
            'orderHeaders' => fn ($q) => $q->orderBy('ordered_at', 'desc')->with(['items.menuItem', 'occupiedZone.row.section', 'orderedBy']),
            'paymentRecords' => fn ($q) => $q->orderBy('recorded_at', 'desc'),
            'billingDocuments' => fn ($q) => $q->orderBy('created_at', 'desc'),
            'favoritedBy',
        ])->findOrFail($this->id);
    }

    public function getChargesTotalProperty(): float
    {
        return $this->group?->chargesTotal() ?? 0;
    }

    public function getPaymentsTotalProperty(): float
    {
        return $this->group?->paymentsTotal() ?? 0;
    }

    public function getBalanceProperty(): float
    {
        return $this->group?->balance() ?? 0;
    }

    public function openAddZoneModal(): void
    {
        $this->showAddZoneModal = true;
        $this->errorMessage = null;
        $this->zoneRowId = null;
        $this->zoneStartSeq = null;
        $this->zoneEndSeq = null;
        $this->deliveryLabel = null;
    }

    public function closeAddZoneModal(): void
    {
        $this->showAddZoneModal = false;
        $this->errorMessage = null;
    }

    public function addZone(): void
    {
        $this->errorMessage = null;

        if (! Auth::user()?->can('floor.assign_zone')) {
            $this->errorMessage = __('Unauthorized to assign zones.');
            return;
        }

        if ($this->group?->is_closed) {
            $this->errorMessage = __('Cannot add zones to a closed group.');
            return;
        }

        $this->validate([
            'zoneRowId' => 'required|integer|exists:rows,id',
            'zoneStartSeq' => 'required|integer|min:1',
            'zoneEndSeq' => 'required|integer|min:1|gte:zoneStartSeq',
        ]);

        $row = Row::findOrFail($this->zoneRowId);

        try {
            app(OccupancyService::class)->assignZone(
                $this->group,
                $row,
                (int) $this->zoneStartSeq,
                (int) $this->zoneEndSeq,
                Auth::user(),
                $this->deliveryLabel,
            );

            $this->showAddZoneModal = false;
            $this->loadGroup();
        } catch (ZoneOverlapException $e) {
            $this->errorMessage = __('Zone overlap: ') . $e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function releaseZone(int $zoneId): void
    {
        $zone = OccupiedZone::findOrFail($zoneId);
        if ($zone->billing_group_id !== $this->group?->id) {
            return;
        }

        if (! Auth::user()?->can('floor.release_zone')) {
            $this->dispatch('notify', message: __('Unauthorized to release zones.'));
            return;
        }

        try {
            app(OccupancyService::class)->releaseZone($zone, Auth::user());
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage());
        }
    }

    public function printBill(): void
    {
        if (! Auth::user()?->can('billing_document.create')) {
            $this->dispatch('notify', message: __('Unauthorized to print bills.'));
            return;
        }

        try {
            app(BillingService::class)->generateInternalBill($this->group, Auth::user());
            $this->dispatch('notify', message: __('Bill sent to printer.'));
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage());
        }
    }

    public function reopenGroup(): void
    {
        if (! Auth::user()?->can('billing_group.reopen')) {
            $this->dispatch('notify', message: __('Unauthorized to reopen groups.'));
            return;
        }

        try {
            app(BillingGroupService::class)->reopen($this->group, Auth::user());
            $this->loadGroup();
            $this->dispatch('notify', message: __('Group reopened.'));
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage());
        }
    }

    public function addOrder(): void
    {
        $this->redirect(route('orders.new', ['billingGroupId' => $this->group->id]), navigate: true);
    }

    public function toggleFavorite(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $pivot = $this->group->favoritedBy()->where('user_id', $user->id)->first();

        if ($pivot) {
            if ($pivot->pivot->is_manual === false) {
                $this->dispatch('notify', message: __('floor.cannot_unfavorite_assigned'));
                return;
            }
            $this->group->favoritedBy()->detach($user->id);
        } else {
            $this->group->favoritedBy()->attach($user->id, ['is_manual' => true]);
        }

        $this->loadGroup();
    }

    public function getIsFavoritedProperty(): bool
    {
        $user = Auth::user();
        if (! $user || ! $this->group) {
            return false;
        }
        return $this->group->favoritedBy->where('id', $user->id)->isNotEmpty();
    }

    public function render()
    {
        return view('livewire.billing-group.billing-group-detail')
            ->layout('layouts.operational');
    }
}
