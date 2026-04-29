<?php

namespace App\Domain\Floor;

use App\Domain\Audit\Audit;
use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\ServiceSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingGroupService
{
    public function open(
        ServiceSession $session,
        User $actor,
        ?int $coverCount = null,
        ?string $notes = null,
    ): BillingGroup {
        if (! $session->isOpen()) {
            throw new RuntimeException('Service session is not open.');
        }

        return DB::transaction(function () use ($session, $actor, $coverCount, $notes) {
            $statusId = BillingStatus::where('code', BillingStatus::ACTIVE)->value('id');

            $next = (int) BillingGroup::where('service_session_id', $session->id)->count() + 1;
            $code = 'G-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);

            $group = BillingGroup::create([
                'service_session_id' => $session->id,
                'display_code'       => $code,
                'billing_status_id'  => $statusId,
                'cover_count'        => $coverCount,
                'notes'              => $notes,
                'opened_by_user_id'  => $actor->id,
                'opened_at'          => now(),
                'is_closed'          => false,
                'version_number'     => 1,
            ]);

            Audit::record(
                'BILLING_GROUP_OPENED',
                "Grupo {$code} aberto por {$actor->name}",
                ['cover_count' => $coverCount],
                ['billing_group_id' => $group->id, 'service_session_id' => $session->id],
            );

            return $group;
        });
    }

    public function setStatus(BillingGroup $group, string $statusCode, User $actor): BillingGroup
    {
        $status = BillingStatus::where('code', $statusCode)->firstOrFail();
        $previous = $group->status?->code;

        if ($previous === $statusCode) {
            return $group;
        }

        $group->update(['billing_status_id' => $status->id]);

        Audit::record(
            'BILLING_GROUP_STATUS_CHANGED',
            "Estado do grupo {$group->display_code}: {$previous} -> {$statusCode}",
            ['from' => $previous, 'to' => $statusCode],
            ['billing_group_id' => $group->id, 'service_session_id' => $group->service_session_id],
        );

        return $group;
    }

    public function close(BillingGroup $group, User $actor): BillingGroup
    {
        DB::transaction(function () use ($group) {
            $group->update([
                'is_closed' => true,
                'closed_at' => now(),
                'billing_status_id' => BillingStatus::where('code', BillingStatus::CLOSED)->value('id'),
            ]);
            $group->openOccupiedZones()->update(['is_open' => false, 'released_at' => now()]);
        });

        Audit::record(
            'BILLING_GROUP_CLOSED',
            "Grupo {$group->display_code} fechado",
            [],
            ['billing_group_id' => $group->id, 'service_session_id' => $group->service_session_id],
        );

        return $group->refresh();
    }

    public function reopen(BillingGroup $group, User $actor): BillingGroup
    {
        if (! $group->is_closed) {
            return $group;
        }
        $statusId = BillingStatus::where('code', BillingStatus::ACTIVE)->value('id');
        $group->update([
            'is_closed' => false,
            'closed_at' => null,
            'billing_status_id' => $statusId,
        ]);

        Audit::record(
            'BILLING_GROUP_REOPENED',
            "Grupo {$group->display_code} reaberto",
            [],
            ['billing_group_id' => $group->id, 'service_session_id' => $group->service_session_id],
        );

        return $group;
    }
}
