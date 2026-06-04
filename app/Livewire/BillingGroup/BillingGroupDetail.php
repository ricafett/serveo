<?php

namespace App\Livewire\BillingGroup;

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Floor\ZoneOverlapException;
use App\Domain\Orders\OrderService;
use App\Models\BillingDocument;
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

    // Payment form
    public ?float $paymentAmount = null;

    public ?string $paymentLabel = 'Cash';

    public ?string $paymentNotes = null;

    // Messages
    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    // Zone release modal
    public bool $showReleaseModal = false;

    public ?int $releaseZoneId = null;

    // Void modal
    public bool $showVoidModal = false;

    public ?int $voidOrderId = null;

    /** @var int[] */
    public array $selectedVoidItemIds = [];

    public ?string $voidReason = null;

    public bool $isSubmitting = false;

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

    // ------------------------------------------------------------------
    // Zone Management
    // ------------------------------------------------------------------

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

    // ------------------------------------------------------------------
    // Zone Release (modal confirmation)
    // ------------------------------------------------------------------

    public function confirmReleaseZone(int $zoneId): void
    {
        $this->releaseZoneId = $zoneId;
        $this->showReleaseModal = true;
        $this->errorMessage = null;
    }

    public function cancelReleaseZone(): void
    {
        $this->showReleaseModal = false;
        $this->releaseZoneId = null;
    }

    public function releaseZone(): void
    {
        if ($this->isSubmitting) {
            return;
        }

        if (! $this->releaseZoneId) {
            return;
        }

        $zone = OccupiedZone::findOrFail($this->releaseZoneId);
        if ($zone->billing_group_id !== $this->group?->id) {
            return;
        }

        if (! Auth::user()?->can('floor.release_zone')) {
            $this->errorMessage = __('Unauthorized to release zones.');

            return;
        }

        try {
            $this->isSubmitting = true;

            app(OccupancyService::class)->releaseZone($zone, Auth::user());
            $this->successMessage = __('Zone released.');
            $this->showReleaseModal = false;
            $this->releaseZoneId = null;
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    // ------------------------------------------------------------------
    // Bill Printing
    // ------------------------------------------------------------------

    public function printBill(): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $this->errorMessage = null;
        $this->successMessage = null;

        if (! Auth::user()?->can('billing_document.create')) {
            $this->errorMessage = __('Unauthorized to print bills.');

            return;
        }

        try {
            $this->isSubmitting = true;

            app(BillingService::class)->generateInternalBill($this->group, Auth::user());
            $this->successMessage = __('Bill sent to printer.');
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function reprintBill(int $billId): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $this->errorMessage = null;
        $this->successMessage = null;

        if (! Auth::user()?->can('billing_document.reprint')) {
            $this->errorMessage = __('Unauthorized to reprint bills.');

            return;
        }

        try {
            $this->isSubmitting = true;

            $original = BillingDocument::findOrFail($billId);
            app(BillingService::class)->reprintBill($original, Auth::user());
            $this->successMessage = __('Bill reprint sent to printer.');
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    // ------------------------------------------------------------------
    // Payment
    // ------------------------------------------------------------------

    public function recordPayment(): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $this->errorMessage = null;
        $this->successMessage = null;

        if (! Auth::user()?->can('payment.record')) {
            $this->errorMessage = __('Unauthorized to record payments.');

            return;
        }

        $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01',
            'paymentLabel' => 'required|string|max:50',
        ]);

        try {
            $this->isSubmitting = true;

            app(BillingService::class)->recordPayment(
                $this->group,
                Auth::user(),
                (float) $this->paymentAmount,
                $this->paymentLabel,
                $this->paymentNotes,
            );
            $this->successMessage = __('Payment recorded.');
            $this->paymentAmount = null;
            $this->paymentNotes = null;
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function fillBalance(): void
    {
        $this->paymentAmount = round($this->balance, 2);
    }

    // ------------------------------------------------------------------
    // Reopen
    // ------------------------------------------------------------------

    public function reopenGroup(): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $this->errorMessage = null;
        $this->successMessage = null;

        if (! Auth::user()?->can('billing_group.reopen')) {
            $this->errorMessage = __('Unauthorized to reopen groups.');

            return;
        }

        try {
            $this->isSubmitting = true;

            app(BillingGroupService::class)->reopen($this->group, Auth::user());
            $this->successMessage = __('Group reopened.');
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    // ------------------------------------------------------------------
    // Orders
    // ------------------------------------------------------------------

    public function addOrder(): void
    {
        $this->redirect(route('orders.new', ['billingGroupId' => $this->group->id]), navigate: true);
    }

    // ------------------------------------------------------------------
    // Delivery toggles
    // ------------------------------------------------------------------

    public function toggleDelivered(int $itemId): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $item = OrderItem::with('header.billingGroup')->findOrFail($itemId);

        if ($item->header->billing_group_id !== $this->group?->id) {
            return;
        }

        if (! Auth::user()?->can('order.mark_delivered')) {
            $this->errorMessage = __('Unauthorized.');

            return;
        }

        if ($item->isVoided()) {
            return;
        }

        try {
            $this->isSubmitting = true;
            $service = app(OrderService::class);

            if ($item->isDelivered()) {
                $service->unmarkDelivered($item, Auth::user());
            } else {
                $service->markDelivered($item, Auth::user());
            }

            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function toggleOrderDelivered(int $orderId): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $order = OrderHeader::with('items', 'billingGroup')->findOrFail($orderId);

        if ($order->billing_group_id !== $this->group?->id) {
            return;
        }

        if (! Auth::user()?->can('order.mark_delivered')) {
            $this->errorMessage = __('Unauthorized.');

            return;
        }

        $nonVoidedItems = $order->items->filter(fn (OrderItem $item) => ! $item->isVoided());

        if ($nonVoidedItems->isEmpty()) {
            return;
        }

        // If all non-voided items are delivered → unmark all. Otherwise → mark all undelivered as delivered.
        $allDelivered = $nonVoidedItems->every(fn (OrderItem $item) => $item->isDelivered());

        try {
            $this->isSubmitting = true;
            $service = app(OrderService::class);

            foreach ($nonVoidedItems as $item) {
                if ($allDelivered) {
                    $service->unmarkDelivered($item, Auth::user());
                } elseif (! $item->isDelivered()) {
                    $service->markDelivered($item, Auth::user());
                }
            }

            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    // ------------------------------------------------------------------
    // Void / Cancel
    // ------------------------------------------------------------------

    public function canVoidOrder(OrderHeader $order): bool
    {
        $user = Auth::user();

        if (! $user || $this->group?->is_closed) {
            return false;
        }

        return $user->can('voidOrder', $order);
    }

    public function getVoidableItemsProperty(): array
    {
        if (! $this->voidOrderId || ! $this->group) {
            return [];
        }

        $order = $this->group->orderHeaders->firstWhere('id', $this->voidOrderId);
        if (! $order) {
            return [];
        }

        return $order->items
            ->filter(fn (OrderItem $item) => ! $item->isVoided() && ! $item->isDelivered())
            ->values()
            ->toArray();
    }

    public function openVoidModal(int $orderId, bool $selectAll = false): void
    {
        $order = OrderHeader::with('items')->findOrFail($orderId);

        if ($order->billing_group_id !== $this->group?->id) {
            $this->errorMessage = __('billing.void_unauthorized');

            return;
        }

        if (! $this->canVoidOrder($order)) {
            $this->errorMessage = __('billing.void_unauthorized');

            return;
        }

        $this->voidOrderId = $orderId;
        $this->voidReason = null;
        $this->showVoidModal = true;

        if ($selectAll) {
            $this->selectedVoidItemIds = $order->items
                ->filter(fn (OrderItem $item) => ! $item->isVoided())
                ->pluck('id')
                ->toArray();
        } else {
            $this->selectedVoidItemIds = [];
        }
    }

    public function closeVoidModal(): void
    {
        $this->showVoidModal = false;
        $this->voidOrderId = null;
        $this->selectedVoidItemIds = [];
        $this->voidReason = null;
    }

    public function confirmVoid(): void
    {
        if ($this->isSubmitting || ! $this->voidOrderId || empty($this->selectedVoidItemIds)) {
            return;
        }

        $this->errorMessage = null;
        $this->successMessage = null;

        $this->validate([
            'voidReason' => 'nullable|string|max:500',
        ]);

        $order = OrderHeader::findOrFail($this->voidOrderId);

        if ($order->billing_group_id !== $this->group?->id) {
            $this->errorMessage = __('billing.void_unauthorized');

            return;
        }

        if (! Auth::user()?->can('voidOrder', $order)) {
            $this->errorMessage = __('billing.void_unauthorized');

            return;
        }

        try {
            $this->isSubmitting = true;

            app(OrderService::class)->voidItems(
                $this->selectedVoidItemIds,
                Auth::user(),
                $this->voidReason,
            );

            $this->successMessage = __('billing.items_voided');
            $this->closeVoidModal();
            $this->loadGroup();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    // ------------------------------------------------------------------
    // Favorites
    // ------------------------------------------------------------------

    public function toggleFavorite(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $pivot = $this->group->favoritedBy()->where('user_id', $user->id)->first();

        if ($pivot) {
            $hasAssignedZone = OccupiedZone::where('billing_group_id', $this->group->id)
                ->where('server_id', $user->id)
                ->where('is_open', true)
                ->exists();

            if ($hasAssignedZone || ($pivot->pivot->is_manual === false)) {
                $this->errorMessage = __('floor.cannot_unfavorite_assigned');

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

    // ------------------------------------------------------------------
    // Lifecycle
    // ------------------------------------------------------------------

    public function refreshData(): void
    {
        $this->loadGroup();
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    public function render()
    {
        return view('livewire.billing-group.billing-group-detail')
            ->layout('layouts.operational');
    }
}
