<?php

namespace App\Livewire\Floor;

use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Floor\ZoneOverlapException;
use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\OccupiedZone;
use App\Models\Row;
use App\Models\Section;
use App\Models\ServiceSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class FloorIndex extends Component
{
    #[Url(as: 'session', keep: true)]
    public ?int $serviceSessionId = null;

    public bool $showCreateModal = false;
    public ?int $selectedRowId = null;
    public ?int $selectedStartSeq = null;
    public ?int $selectedEndSeq = null;

    // Create billing group form
    public ?string $statusCode = null;
    public ?int $coverCount = null;
    public ?string $notes = null;

    // Zone assignment form
    public ?int $zoneRowId = null;
    public ?int $zoneStartSeq = null;
    public ?int $zoneEndSeq = null;
    public ?string $deliveryLabel = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        if (! $this->serviceSessionId) {
            $session = ServiceSession::where('status', 'OPEN')->latest('starts_at')->first();
            $this->serviceSessionId = $session?->id;
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
            'rows.occupiedZones' => fn ($q) => $q->where('is_open', true)->with('billingGroup.status'),
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

        return BillingGroup::with([
            'status',
            'occupiedZones' => fn ($q) => $q->where('is_open', true)->with('row.section'),
        ])
            ->where('service_session_id', $session->id)
            ->where('is_closed', false)
            ->orderBy('opened_at', 'desc')
            ->get();
    }

    /**
     * Build an occupancy map for a row: array keyed by pair_sequence => 'free' or group_id.
     */
    public function getRowOccupancyMap(Row $row): array
    {
        $map = [];
        foreach ($row->seatPairs as $pair) {
            $map[$pair->pair_sequence] = ['status' => 'free', 'pair' => $pair];
        }

        foreach ($row->occupiedZones as $zone) {
            for ($seq = $zone->start_seat_pair_sequence; $seq <= $zone->end_seat_pair_sequence; $seq++) {
                if (isset($map[$seq])) {
                    $map[$seq]['status'] = 'occupied';
                    $map[$seq]['zone'] = $zone;
                    $map[$seq]['group'] = $zone->billingGroup;
                }
            }
        }

        return $map;
    }

    /**
     * Build contiguous ranges from the occupancy map.
     * Returns array of ['type' => 'free'|'occupied', 'start' => int, 'end' => int, 'zone' => ?, 'group' => ?].
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

        // Sort by pair sequence
        ksort($map);

        foreach ($map as $seq => $data) {
            $type = $data['status'];
            $zone = $data['zone'] ?? null;
            $group = $data['group'] ?? null;

            if ($currentType === null) {
                $currentType = $type;
                $currentStart = $seq;
                $currentZone = $zone;
                $currentGroup = $group;
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
            ];

            $currentType = $type;
            $currentStart = $seq;
            $currentZone = $zone;
            $currentGroup = $group;
        }

        // Close last range
        $lastSeq = array_key_last($map);
        $ranges[] = [
            'type' => $currentType,
            'start' => $currentStart,
            'end' => $lastSeq,
            'zone' => $currentZone,
            'group' => $currentGroup,
        ];

        return $ranges;
    }

    public function selectRange(int $rowId, int $startSeq, int $endSeq): void
    {
        $this->selectedRowId = $rowId;
        $this->selectedStartSeq = $startSeq;
        $this->selectedEndSeq = $endSeq;
        $this->zoneRowId = $rowId;
        $this->zoneStartSeq = $startSeq;
        $this->zoneEndSeq = $endSeq;
        $this->showCreateModal = true;
        $this->errorMessage = null;
    }

    public function openExistingGroup(int $groupId): void
    {
        $this->redirect(route('billing-groups.detail', ['id' => $groupId]), navigate: true);
    }

    public function createBillingGroup(): void
    {
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
            'statusCode' => 'required|string',
            'coverCount' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:500',
            'zoneRowId' => 'required|integer|exists:rows,id',
            'zoneStartSeq' => 'required|integer|min:1',
            'zoneEndSeq' => 'required|integer|min:1|gte:zoneStartSeq',
        ]);

        $row = Row::findOrFail($this->zoneRowId);

        try {
            $service = app(BillingGroupService::class);
            $group = $service->open(
                $session,
                Auth::user(),
                $this->coverCount,
                $this->notes,
                $this->statusCode,
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
            $this->reset(['statusCode', 'coverCount', 'notes', 'selectedRowId', 'selectedStartSeq', 'selectedEndSeq', 'zoneRowId', 'zoneStartSeq', 'zoneEndSeq', 'deliveryLabel']);
            $this->statusCode = BillingStatus::ACTIVE;

            $this->redirect(route('billing-groups.detail', ['id' => $group->id]), navigate: true);
        } catch (ZoneOverlapException $e) {
            $this->errorMessage = __('Zone overlap: ') . $e->getMessage();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function closeModal(): void
    {
        $this->showCreateModal = false;
        $this->errorMessage = null;
        $this->reset(['statusCode', 'coverCount', 'notes', 'selectedRowId', 'selectedStartSeq', 'selectedEndSeq', 'zoneRowId', 'zoneStartSeq', 'zoneEndSeq', 'deliveryLabel']);
        $this->statusCode = BillingStatus::ACTIVE;
    }

    public function render()
    {
        return view('livewire.floor.floor-index')
            ->layout('layouts.operational');
    }
}
