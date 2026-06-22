<?php

namespace App\Domain\Floor;

use App\Domain\Audit\Audit;
use App\Domain\ChecksPermissions;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
use App\Models\Row;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OccupancyService
{
    use ChecksPermissions;

    /**
     * Open a new occupied zone for a billing group, enforcing the no-overlap rule
     * inside the same row. The whole operation runs in a transaction so two
     * simultaneous server submissions cannot both win.
     */
    public function assignZone(
        BillingGroup $group,
        Row $row,
        int $startSeq,
        int $endSeq,
        User $actor,
        ?string $deliveryCenterLabel = null,
        ?User $assignedServer = null,
    ): OccupiedZone {
        $this->ensureCan($actor, 'floor.assign_zone');

        if ($assignedServer && (! $assignedServer->hasRole('SERVER') || ! $assignedServer->is_active)) {
            throw new RuntimeException(__('floor.assigned_server_invalid'));
        }

        if (! $group->serviceSession?->isOpen()) {
            throw new RuntimeException('No open service session. Operations require an active session.');
        }

        if ($startSeq > $endSeq) {
            throw new RuntimeException('start_seat_pair_sequence must be <= end_seat_pair_sequence');
        }

        return DB::transaction(function () use ($group, $row, $startSeq, $endSeq, $actor, $deliveryCenterLabel, $assignedServer) {
            $conflict = OccupiedZone::where('row_id', $row->id)
                ->where('is_open', true)
                ->whereHas('billingGroup', fn ($query) => $query->where('service_session_id', $group->service_session_id))
                ->where('start_seat_pair_sequence', '<=', $endSeq)
                ->where('end_seat_pair_sequence', '>=', $startSeq)
                ->lockForUpdate()
                ->first();

            if ($conflict) {
                throw new ZoneOverlapException(
                    "Range overlaps zone #{$conflict->id} ({$conflict->rangeLabel()})"
                );
            }

            $zoneServer = $assignedServer ?? $actor;

            $zone = OccupiedZone::create([
                'billing_group_id' => $group->id,
                'row_id' => $row->id,
                'start_seat_pair_sequence' => $startSeq,
                'end_seat_pair_sequence' => $endSeq,
                'default_delivery_mode' => 'CENTER',
                'delivery_center_label' => $deliveryCenterLabel,
                'opened_at' => now(),
                'is_open' => true,
                'created_by_user_id' => $actor->id,
                'server_id' => $zoneServer->id,
            ]);

            Audit::record(
                'OCCUPIED_ZONE_OPENED',
                "Zona aberta {$zone->rangeLabel()} para grupo {$group->display_code}",
                ['start' => $startSeq, 'end' => $endSeq, 'row_id' => $row->id],
                ['billing_group_id' => $group->id, 'occupied_zone_id' => $zone->id, 'service_session_id' => $group->service_session_id, 'actor_user_id' => $actor->id],
            );

            // Auto-favorite: the assigned server gets the billing group as a favorite.
            // is_manual = false means it cannot be unfavorited while assigned.
            if ($group->favoritedBy()->where('user_id', $zoneServer->id)->exists()) {
                $group->favoritedBy()->updateExistingPivot($zoneServer->id, ['is_manual' => false]);
            } else {
                $group->favoritedBy()->attach($zoneServer->id, ['is_manual' => false]);
            }

            return $zone;
        });
    }

    public function releaseZone(OccupiedZone $zone, User $actor): void
    {
        $this->ensureCan($actor, 'floor.release_zone');

        if (! $zone->billingGroup?->serviceSession?->isOpen()) {
            throw new RuntimeException('No open service session. Operations require an active session.');
        }

        DB::transaction(function () use ($zone, $actor) {
            if (! $zone->is_open) {
                return;
            }
            $zone->update([
                'is_open' => false,
                'released_at' => now(),
            ]);
            Audit::record(
                'OCCUPIED_ZONE_RELEASED',
                "Zona libertada (#{$zone->id})",
                ['released_by' => $actor->id],
                ['billing_group_id' => $zone->billing_group_id, 'occupied_zone_id' => $zone->id, 'service_session_id' => $zone->billingGroup?->service_session_id, 'actor_user_id' => $actor->id],
            );
        });
    }
}
