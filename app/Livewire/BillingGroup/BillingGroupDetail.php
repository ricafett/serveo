<?php

namespace App\Livewire\BillingGroup;

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Floor\ZoneOverlapException;
use App\Domain\Orders\OrderService;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
use App\Models\OrderHeader;
use App\Models\OrderItem;
use App\Models\Row;
use App\Models\User;
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

    public ?int $assignedServerId = null;

    public ?string $errorMessage = null;

    // Status change
    public ?string $newStatusCode = null;

    public bool $isSubmitting = false;

    public bool $showVoidItemModal = false;

    public bool $showVoidOrderModal = false;

    public ?int $voidItemId = null;

    public ?int $voidOrderId = null;

    public ?string $voidReason = null;

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

    public function getAvailableServersProperty()
    {
        return User::role('SERVER')
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('username')
            ->get();
    }

    public function shouldSelectAssignedServer(): bool
    {
        return Auth::user()?->hasRole('CASHIER') ?? false;
    }

    private function resolveAssignedServer(): ?User
    {
        if (! $this->shouldSelectAssignedServer()) {
            return null;
        }

        if (! $this->assignedServerId) {
            throw new \RuntimeException(__('floor.assigned_server_required'));
        }

        $assignedServer = User::findOrFail($this->assignedServerId);

        if (! $assignedServer->hasRole('SERVER') || ! $assignedServer->is_active) {
            throw new \RuntimeException(__('floor.assigned_server_invalid'));
        }

        return $assignedServer;
    }

    public function openAddZoneModal(): void
    {
        $this->showAddZoneModal = true;
        $this->errorMessage = null;
        $this->zoneRowId = null;
        $this->zoneStartSeq = null;
        $this->zoneEndSeq = null;
        $this->deliveryLabel = null;
        $this->assignedServerId = null;
    }

    public function closeAddZoneModal(): void
    {
        $this->showAddZoneModal = false;
        $this->errorMessage = null;
        $this->assignedServerId = null;
    }

    public function addZone(): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $this->errorMessage = null;

        if (! Auth::user()?->can('floor.assign_zone')) {
            $this->errorMessage = __('Unauthorized to assign zones.');

            return;
        }

        if ($this->group?->is_closed) {
            $this->errorMessage = __('Cannot add zones to a closed group.');

            return;
        }

        $this->validate(
            [
                'zoneRowId' => 'required|integer|exists:rows,id',
                'zoneStartSeq' => 'required|integer|min:1',
                'zoneEndSeq' => 'required|integer|min:1|gte:zoneStartSeq',
                'assignedServerId' => $this->shouldSelectAssignedServer() ? 'required|integer|exists:users,id' : 'nullable|integer|exists:users,id',
            ],
            [
                'assignedServerId.required' => __('floor.assigned_server_required'),
            ],
        );

        $row = Row::findOrFail($this->zoneRowId);

        try {
            $this->isSubmitting = true;

            $assignedServer = $this->resolveAssignedServer();

            app(OccupancyService::class)->assignZone(
                $this->group,
                $row,
                (int) $this->zoneStartSeq,
                (int) $this->zoneEndSeq,
                Auth::user(),
                $this->deliveryLabel,
                $assignedServer,
            );

            $this->showAddZoneModal = false;
            $this->loadGroup();
        } catch (ZoneOverlapException $e) {
            $this->errorMessage = __('Zone overlap: ').$e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function releaseZone(int $zoneId): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $zone = OccupiedZone::findOrFail($zoneId);
        if ($zone->billing_group_id !== $this->group?->id) {
            return;
        }

        if (! Auth::user()?->can('floor.release_zone')) {
            $this->dispatch('notify', message: __('Unauthorized to release zones.'));

            return;
        }

        try {
            $this->isSubmitting = true;

            app(OccupancyService::class)->releaseZone($zone, Auth::user());
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage());
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function printBill(): void
    {
        if ($this->isSubmitting) {
            return;
        }

        if (! Auth::user()?->can('billing_document.create')) {
            $this->dispatch('notify', message: __('Unauthorized to print bills.'));

            return;
        }

        try {
            $this->isSubmitting = true;

            app(BillingService::class)->generateInternalBill($this->group, Auth::user());
            $this->dispatch('notify', message: __('Bill sent to printer.'));
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage());
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function reopenGroup(): void
    {
        if ($this->isSubmitting) {
            return;
        }

        if (! Auth::user()?->can('billing_group.reopen')) {
            $this->dispatch('notify', message: __('Unauthorized to reopen groups.'));

            return;
        }

        try {
            $this->isSubmitting = true;

            app(BillingGroupService::class)->reopen($this->group, Auth::user());
            $this->loadGroup();
            $this->dispatch('notify', message: __('Group reopened.'));
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage());
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function addOrder(): void
    {
        $this->redirect(route('orders.new', ['billingGroupId' => $this->group->id]), navigate: true);
    }

    public function canVoidOrder(OrderHeader $order): bool
    {
        $user = Auth::user();

        if (! $user || $this->group?->is_closed) {
            return false;
        }

        return $user->can('voidOrder', $order);
    }

    public function canVoidItem(OrderItem $item): bool
    {
        if ($item->isVoided()) {
            return false;
        }

        return $this->canVoidOrder($item->header);
    }

    public function openVoidItemModal(int $itemId): void
    {
        $item = OrderItem::with('header')->findOrFail($itemId);

        if ($item->header->billing_group_id !== $this->group?->id) {
            $this->dispatch('notify', message: __('billing.void_unauthorized'));

            return;
        }

        if (! $this->canVoidItem($item)) {
            $this->dispatch('notify', message: __('billing.void_unauthorized'));

            return;
        }

        $this->voidItemId = $itemId;
        $this->voidOrderId = null;
        $this->voidReason = null;
        $this->showVoidItemModal = true;
    }

    public function openVoidOrderModal(int $orderId): void
    {
        $order = OrderHeader::with('items')->findOrFail($orderId);

        if ($order->billing_group_id !== $this->group?->id) {
            $this->dispatch('notify', message: __('billing.void_unauthorized'));

            return;
        }

        if (! $this->canVoidOrder($order)) {
            $this->dispatch('notify', message: __('billing.void_unauthorized'));

            return;
        }

        $this->voidOrderId = $orderId;
        $this->voidItemId = null;
        $this->voidReason = null;
        $this->showVoidOrderModal = true;
    }

    public function closeVoidModal(): void
    {
        $this->showVoidItemModal = false;
        $this->showVoidOrderModal = false;
        $this->voidItemId = null;
        $this->voidOrderId = null;
        $this->voidReason = null;
    }

    public function confirmVoidItem(): void
    {
        if ($this->isSubmitting || ! $this->voidItemId) {
            return;
        }

        $this->validate([
            'voidReason' => 'nullable|string|max:500',
        ]);

        $item = OrderItem::with('header')->findOrFail($this->voidItemId);

        if ($item->header->billing_group_id !== $this->group?->id) {
            $this->dispatch('notify', message: __('billing.void_unauthorized'));

            return;
        }

        if (! Auth::user()?->can('voidItem', $item->header)) {
            $this->dispatch('notify', message: __('billing.void_unauthorized'));

            return;
        }

        try {
            $this->isSubmitting = true;

            app(OrderService::class)->voidItem($item, Auth::user(), $this->voidReason);
            $this->closeVoidModal();
            $this->loadGroup();
            $this->dispatch('notify', message: __('billing.item_voided'));
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage());
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function confirmVoidOrder(): void
    {
        if ($this->isSubmitting || ! $this->voidOrderId) {
            return;
        }

        $this->validate([
            'voidReason' => 'nullable|string|max:500',
        ]);

        $order = OrderHeader::findOrFail($this->voidOrderId);

        if ($order->billing_group_id !== $this->group?->id) {
            $this->dispatch('notify', message: __('billing.void_unauthorized'));

            return;
        }

        if (! Auth::user()?->can('voidOrder', $order)) {
            $this->dispatch('notify', message: __('billing.void_unauthorized'));

            return;
        }

        try {
            $this->isSubmitting = true;

            app(OrderService::class)->voidOrder($order, Auth::user(), $this->voidReason);
            $this->closeVoidModal();
            $this->loadGroup();
            $this->dispatch('notify', message: __('billing.order_voided'));
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage());
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function toggleFavorite(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $pivot = $this->group->favoritedBy()->where('user_id', $user->id)->first();

        if ($pivot) {
            // Cannot unfavorite if this server has open zones assigned to this group.
            $hasAssignedZone = OccupiedZone::where('billing_group_id', $this->group->id)
                ->where('server_id', $user->id)
                ->where('is_open', true)
                ->exists();

            if ($hasAssignedZone || ($pivot->pivot->is_manual === false)) {
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

    public function refreshData(): void
    {
        $this->loadGroup();
    }

    public function render()
    {
        return view('livewire.billing-group.billing-group-detail')
            ->layout('layouts.operational');
    }
}
