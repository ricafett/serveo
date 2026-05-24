<?php

namespace App\Livewire\Floor;

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Floor\ZoneOverlapException;
use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\Row;
use App\Models\Section;
use App\Models\ServiceSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;

class FloorIndex extends Component
{
    #[Url(as: 'session', keep: true)]
    public ?int $serviceSessionId = null;

    /** When true, show only favorited groups (occupied) and only free seats assigned to this server. */
    #[Url(as: 'fav', keep: true)]
    #[Session(key: 'floor.favorites_only')]
    public bool $favoritesOnly = false;

    /** When false, hide all free seat ranges from the map. Does not affect the open groups list. */
    #[Url(as: 'free', keep: true)]
    #[Session(key: 'floor.show_free_seats')]
    public bool $showFreeSeats = true;

    public bool $showCreateModal = false;

    public ?int $selectedRowId = null;

    public ?int $selectedStartSeq = null;

    public ?int $selectedEndSeq = null;

    // Create billing group form
    public ?string $name = null;

    public ?string $statusCode = null;

    public ?int $coverCount = null;

    public ?string $notes = null;

    // Zone assignment form
    public ?int $zoneRowId = null;

    public ?int $zoneStartSeq = null;

    public ?int $zoneEndSeq = null;

    public ?int $zoneSeatCount = null;

    public ?string $zoneEndLabel = null;

    public ?string $deliveryLabel = null;

    public ?string $errorMessage = null;

    public bool $isSubmitting = false;

    public function mount(): void
    {
        // URL params take priority over session-stored preferences
        if (request()->has('fav')) {
            $this->favoritesOnly = filter_var(request()->query('fav'), FILTER_VALIDATE_BOOLEAN);
        }
        if (request()->has('free')) {
            $this->showFreeSeats = filter_var(request()->query('free'), FILTER_VALIDATE_BOOLEAN);
        }

        if (! $this->serviceSessionId) {
            $session = ServiceSession::where('status', 'OPEN')->latest('starts_at')->first();
            $this->serviceSessionId = $session?->id;
        }

        if (! $this->serviceSessionId) {
            $this->redirect(route('home'), navigate: true);

            return;
        }

        $this->statusCode = BillingStatus::ACTIVE;
    }

    public function getSessionProperty(): ?ServiceSession
    {
        return $this->serviceSessionId
            ? ServiceSession::find($this->serviceSessionId)
            : null;
    }

    public function getSectionsProperty()
    {
        return Section::with([
            'rows' => fn ($q) => $q->orderBy('sort_order')->where('is_active', true),
            'rows.seatPairs' => fn ($q) => $q->orderBy('pair_sequence')->where('is_active', true),
            'rows.occupiedZones' => fn ($q) => $q->where('is_open', true)->with(['billingGroup.status', 'server']),
        ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function getOpenGroupsProperty()
    {
        $session = $this->session;
        if (! $session) {
            return collect();
        }

        $user = Auth::user();

        $query = BillingGroup::with([
            'status',
            'occupiedZones' => fn ($q) => $q->where('is_open', true)->with(['row.section', 'server']),
            'favoritedBy' => fn ($q) => $q->where('user_id', $user?->id),
        ])
            ->where('service_session_id', $session->id)
            ->where('is_closed', false);

        if ($this->favoritesOnly) {
            $query->whereHas('favoritedBy', fn ($q) => $q->where('user_id', $user?->id));
        }

        return $query->orderBy('opened_at', 'desc')->get();
    }

    /** IDs of billing groups favorited by the current user (used for floor map filtering). */
    public function getFavoriteGroupIdsProperty(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        return BillingGroup::whereHas('favoritedBy', fn ($q) => $q->where('user_id', $user->id))
            ->where('is_closed', false)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Build an occupancy map for a row: array keyed by pair_sequence => 'free' or group_id.
     */
    public function getRowOccupancyMap(Row $row): array
    {
        $map = [];
        foreach ($row->seatPairs as $pair) {
            $map[$pair->pair_sequence] = ['status' => 'free', 'pair' => $pair, 'default_server_id' => $pair->default_server_id];
        }

        foreach ($row->occupiedZones as $zone) {
            for ($seq = $zone->start_seat_pair_sequence; $seq <= $zone->end_seat_pair_sequence; $seq++) {
                if (isset($map[$seq])) {
                    $map[$seq]['status'] = 'occupied';
                    $map[$seq]['zone'] = $zone;
                    $map[$seq]['group'] = $zone->billingGroup;
                    $map[$seq]['server'] = $zone->server;
                }
            }
        }

        return $map;
    }

    /**
     * Build contiguous ranges from the occupancy map.
     * Returns array of ['type' => 'free'|'occupied', 'start' => int, 'end' => int, 'zone' => ?, 'group' => ?, 'server' => ?, 'default_server_id' => int|null].
     */
    public function getRowRanges(Row $row): array
    {
        $map = $this->getRowOccupancyMap($row);
        if (empty($map)) {
            return [];
        }

        $ranges = [];
        $currentType = null;
        $currentStart = null;
        $currentZone = null;
        $currentGroup = null;
        $currentServer = null;
        $currentDefaultServerId = null;

        // Sort by pair sequence
        ksort($map);

        foreach ($map as $seq => $data) {
            $type = $data['status'];
            $zone = $data['zone'] ?? null;
            $group = $data['group'] ?? null;
            $server = $data['server'] ?? null;
            $defaultServerId = $data['default_server_id'] ?? null;

            if ($currentType === null) {
                $currentType = $type;
                $currentStart = $seq;
                $currentZone = $zone;
                $currentGroup = $group;
                $currentServer = $server;
                $currentDefaultServerId = $defaultServerId;

                continue;
            }

            if ($type === $currentType && $zone?->id === $currentZone?->id) {
                // Continue current range
                continue;
            }

            // End current range
            $ranges[] = [
                'type' => $currentType,
                'start' => $currentStart,
                'end' => $seq - 1,
                'zone' => $currentZone,
                'group' => $currentGroup,
                'server' => $currentServer,
                'default_server_id' => $currentDefaultServerId,
            ];

            $currentType = $type;
            $currentStart = $seq;
            $currentZone = $zone;
            $currentGroup = $group;
            $currentServer = $server;
            $currentDefaultServerId = $defaultServerId;
        }

        // Close last range
        $lastSeq = array_key_last($map);
        $ranges[] = [
            'type' => $currentType,
            'start' => $currentStart,
            'end' => $lastSeq,
            'zone' => $currentZone,
            'group' => $currentGroup,
            'server' => $currentServer,
            'default_server_id' => $currentDefaultServerId,
        ];

        return $ranges;
    }

    public function selectPair(int $rowId, int $pairSeq): void
    {
        $this->selectedRowId = $rowId;
        $this->selectedStartSeq = $pairSeq;
        $this->selectedEndSeq = $pairSeq;
        $this->zoneRowId = $rowId;
        $this->zoneStartSeq = $pairSeq;
        $this->zoneEndSeq = $pairSeq;
        $this->zoneSeatCount = 1;
        $this->zoneEndLabel = $this->locationForRowAndSeq($rowId, $pairSeq);
        $this->showCreateModal = true;
        $this->errorMessage = null;
    }

    public function locationForRowAndSeq(int $rowId, int $seq): string
    {
        $row = Row::with('section')->find($rowId);

        return ($row?->section?->section_code ?? '').($row?->row_code ?? '').str_pad((string) $seq, 2, '0', STR_PAD_LEFT);
    }

    public function getRowDisplayItems(Row $row): array
    {
        $map = $this->getRowOccupancyMap($row);
        if (empty($map)) {
            return [];
        }

        $items = [];
        $currentZoneId = null;
        $currentStart = null;
        $currentZone = null;
        $currentGroup = null;
        $currentServer = null;

        ksort($map);

        foreach ($map as $seq => $data) {
            if ($data['status'] === 'free') {
                if ($currentZoneId !== null) {
                    $items[] = [
                        'type' => 'occupied',
                        'start' => $currentStart,
                        'end' => $seq - 1,
                        'zone' => $currentZone,
                        'group' => $currentGroup,
                        'server' => $currentServer,
                    ];
                    $currentZoneId = null;
                }
                $items[] = [
                    'type' => 'free',
                    'start' => $seq,
                    'end' => $seq,
                    'pair' => $data['pair'],
                    'default_server_id' => $data['default_server_id'] ?? null,
                ];
            } else {
                $zone = $data['zone'];
                if ($zone?->id !== $currentZoneId) {
                    if ($currentZoneId !== null) {
                        $items[] = [
                            'type' => 'occupied',
                            'start' => $currentStart,
                            'end' => $seq - 1,
                            'zone' => $currentZone,
                            'group' => $currentGroup,
                            'server' => $currentServer,
                        ];
                    }
                    $currentZoneId = $zone?->id;
                    $currentStart = $seq;
                    $currentZone = $zone;
                    $currentGroup = $data['group'] ?? null;
                    $currentServer = $data['server'] ?? null;
                }
            }
        }

        if ($currentZoneId !== null) {
            $lastSeq = array_key_last($map);
            $items[] = [
                'type' => 'occupied',
                'start' => $currentStart,
                'end' => $lastSeq,
                'zone' => $currentZone,
                'group' => $currentGroup,
                'server' => $currentServer,
            ];
        }

        return $items;
    }

    public function updatedZoneSeatCount(int|string|null $value): void
    {
        if ($value === null || $value === '' || (int) $value < 1 || ! $this->zoneRowId || ! $this->zoneStartSeq) {
            return;
        }

        $count = (int) $value;
        $row = Row::with('seatPairs')->find($this->zoneRowId);
        $maxSeq = $row?->seatPairs->max('pair_sequence') ?? 0;

        $endSeq = $this->zoneStartSeq + $count - 1;
        if ($endSeq > $maxSeq) {
            $endSeq = $maxSeq;
            $this->errorMessage = __('floor.range_too_large', ['max' => $maxSeq - $this->zoneStartSeq + 1]);
        } else {
            $this->errorMessage = null;
        }

        $this->zoneEndSeq = $endSeq;
        $this->zoneEndLabel = $this->locationForRowAndSeq($this->zoneRowId, $endSeq);
    }

    public function updatedZoneEndLabel(?string $value): void
    {
        $this->errorMessage = null;

        if ($value === null || $value === '' || ! $this->zoneRowId || ! $this->zoneStartSeq) {
            return;
        }

        $row = Row::with(['section', 'seatPairs'])->find($this->zoneRowId);
        $prefix = ($row?->section?->section_code ?? '').($row?->row_code ?? '');

        if (! str_starts_with($value, $prefix)) {
            $this->errorMessage = __('floor.invalid_end_pair');

            return;
        }

        $seqString = substr($value, strlen($prefix));
        $seq = (int) $seqString;

        $maxSeq = $row?->seatPairs->max('pair_sequence') ?? 0;
        if ($seq < $this->zoneStartSeq || $seq > $maxSeq) {
            $this->errorMessage = __('floor.invalid_end_pair');

            return;
        }

        $this->zoneEndSeq = $seq;
        $this->zoneSeatCount = $seq - $this->zoneStartSeq + 1;
    }

    public function rowHasVisibleRanges(Row $row): bool
    {
        $ranges = $this->getRowRanges($row);
        $favoriteGroupIds = $this->favoriteGroupIds;
        $favoritesOnly = $this->favoritesOnly;
        $showFreeSeats = $this->showFreeSeats;
        $userId = auth()->id();

        foreach ($ranges as $range) {
            if ($range['type'] === 'free') {
                if ($showFreeSeats && (! $favoritesOnly || ($range['default_server_id'] ?? null) === $userId)) {
                    return true;
                }
            } else {
                if (! $favoritesOnly || ($range['group'] && in_array($range['group']->id, $favoriteGroupIds))) {
                    return true;
                }
            }
        }

        return false;
    }

    public function openExistingGroup(int $groupId): void
    {
        $this->redirect(route('billing-groups.detail', ['id' => $groupId]), navigate: true);
    }

    public function createBillingGroup(): void
    {
        if ($this->isSubmitting) {
            return;
        }

        $this->errorMessage = null;

        if (! Auth::user()?->can('floor.open_billing_group')) {
            $this->errorMessage = __('Unauthorized to open billing group.');

            return;
        }

        $session = $this->session;
        if (! $session) {
            $this->errorMessage = __('No open service session.');

            return;
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'statusCode' => 'required|string',
            'coverCount' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:500',
            'zoneRowId' => 'required|integer|exists:rows,id',
            'zoneStartSeq' => 'required|integer|min:1',
            'zoneSeatCount' => 'required|integer|min:1',
        ]);

        $row = Row::findOrFail($this->zoneRowId);

        if ($this->zoneEndSeq && $this->zoneEndSeq < $this->zoneStartSeq) {
            $this->errorMessage = __('floor.invalid_end_pair');

            return;
        }

        try {
            $this->isSubmitting = true;

            $service = app(BillingGroupService::class);
            $group = $service->open(
                $session,
                Auth::user(),
                $this->coverCount,
                $this->notes,
                $this->statusCode,
                $this->name,
            );

            app(OccupancyService::class)->assignZone(
                $group,
                $row,
                (int) $this->zoneStartSeq,
                (int) $this->zoneEndSeq,
                Auth::user(),
                $this->deliveryLabel,
            );

            $this->showCreateModal = false;
            $this->reset(['name', 'statusCode', 'coverCount', 'notes', 'selectedRowId', 'selectedStartSeq', 'selectedEndSeq', 'zoneRowId', 'zoneStartSeq', 'zoneEndSeq', 'zoneSeatCount', 'zoneEndLabel', 'deliveryLabel']);
            $this->statusCode = BillingStatus::ACTIVE;

            $this->redirect(route('billing-groups.detail', ['id' => $group->id]), navigate: true);
        } catch (ZoneOverlapException $e) {
            $this->errorMessage = __('Zone overlap: ').$e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function closeModal(): void
    {
        $this->showCreateModal = false;
        $this->errorMessage = null;
        $this->reset(['name', 'statusCode', 'coverCount', 'notes', 'selectedRowId', 'selectedStartSeq', 'selectedEndSeq', 'zoneRowId', 'zoneStartSeq', 'zoneEndSeq', 'zoneSeatCount', 'zoneEndLabel', 'deliveryLabel']);
        $this->statusCode = BillingStatus::ACTIVE;
    }

    public function render()
    {
        return view('livewire.floor.floor-index')
            ->layout('layouts.operational');
    }
}
