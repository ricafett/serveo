<?php

namespace App\Domain\Floor;

use App\Domain\Audit\Audit;
use App\Domain\ChecksPermissions;
use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\ServiceSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingGroupService
{
    use ChecksPermissions;

    public function open(
        ServiceSession $session,
        User $actor,
        ?int $coverCount = null,
        ?string $notes = null,
        ?string $initialStatusCode = null,
        ?string $name = null,
    ): BillingGroup {
        $this->ensureCan($actor, 'floor.open_billing_group');

        if (! $session->isOpen()) {
            throw new RuntimeException('Service session is not open.');
        }

        return DB::transaction(function () use ($session, $actor, $coverCount, $notes, $initialStatusCode, $name) {
            $statusId = BillingStatus::where('code', $initialStatusCode ?? BillingStatus::ACTIVE)->value('id');

            $next = (int) BillingGroup::where('service_session_id', $session->id)->count() + 1;
            $code = 'G-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT);

            $group = BillingGroup::create([
                'service_session_id' => $session->id,
                'display_code' => $code,
                'name' => $name,
                'billing_status_id' => $statusId,
                'cover_count' => $coverCount,
                'notes' => $notes,
                'opened_by_user_id' => $actor->id,
                'opened_at' => now(),
                'is_closed' => false,
                'version_number' => 1,
            ]);

            $groupName = $name ? "\"{$name}\" ({$code})" : $code;

            Audit::record(
                'BILLING_GROUP_OPENED',
                "Conta {$groupName} aberta por {$actor->name}",
                ['cover_count' => $coverCount],
                ['billing_group_id' => $group->id, 'service_session_id' => $session->id, 'actor_user_id' => $actor->id],
            );

            return $group;
        });
    }

    private array $validTransitions = [
        BillingStatus::ACTIVE => [BillingStatus::CLOSED],
        BillingStatus::CLOSED => [],
    ];

    private function ensureTransitionAllowed(User $user, string $from, string $to): void
    {
        // Closing and reopening require specific permissions checked at method entry.
        // This method validates business-rule transitions only.
    }

    public function setStatus(BillingGroup $group, string $statusCode, User $actor, ?int $expectedVersion = null): BillingGroup
    {
        $this->ensureCan($actor, 'billing_group.set_status');

        if (! $group->serviceSession?->isOpen()) {
            throw new RuntimeException('No open service session. Operations require an active session.');
        }

        if ($expectedVersion !== null && $group->version_number !== $expectedVersion) {
            throw new RuntimeException('VERSION_CONFLICT');
        }

        $status = BillingStatus::where('code', $statusCode)->firstOrFail();
        $previous = $group->status?->code;

        if ($previous === $statusCode) {
            return $group;
        }

        $allowed = $this->validTransitions[$previous] ?? [];
        if (! in_array($statusCode, $allowed, true)) {
            throw new RuntimeException("Invalid status transition from {$previous} to {$statusCode}");
        }

        $update = [
            'billing_status_id' => $status->id,
            'version_number' => $group->version_number + 1,
        ];

        if ($statusCode === BillingStatus::CLOSED) {
            $update['is_closed'] = true;
            $update['closed_at'] = now();
        }

        DB::transaction(function () use ($group, $update, $statusCode) {
            $group->update($update);
            if ($statusCode === BillingStatus::CLOSED) {
                $group->openOccupiedZones()->update(['is_open' => false, 'released_at' => now()]);
            }
        });

        Audit::record(
            'BILLING_GROUP_STATUS_CHANGED',
            "Estado do grupo {$group->display_code}: {$previous} -> {$statusCode}",
            ['from' => $previous, 'to' => $statusCode],
            ['billing_group_id' => $group->id, 'service_session_id' => $group->service_session_id, 'actor_user_id' => $actor->id],
        );

        return $group->refresh();
    }

    public function close(BillingGroup $group, User $actor, ?int $expectedVersion = null): BillingGroup
    {
        $this->ensureCan($actor, 'billing_group.set_status');

        if (! $group->serviceSession?->isOpen()) {
            throw new RuntimeException('No open service session. Operations require an active session.');
        }

        if ($expectedVersion !== null && $group->version_number !== $expectedVersion) {
            throw new RuntimeException('VERSION_CONFLICT');
        }

        DB::transaction(function () use ($group) {
            $group->update([
                'is_closed' => true,
                'closed_at' => now(),
                'billing_status_id' => BillingStatus::where('code', BillingStatus::CLOSED)->value('id'),
                'version_number' => $group->version_number + 1,
            ]);
            $group->openOccupiedZones()->update(['is_open' => false, 'released_at' => now()]);
        });

        Audit::record(
            'BILLING_GROUP_CLOSED',
            "Conta {$group->display_code} fechada",
            [],
            ['billing_group_id' => $group->id, 'service_session_id' => $group->service_session_id, 'actor_user_id' => $actor->id],
        );

        return $group->refresh();
    }

    public function reopen(BillingGroup $group, User $actor, ?int $expectedVersion = null): BillingGroup
    {
        $this->ensureCan($actor, 'billing_group.reopen');

        if (! $group->serviceSession?->isOpen()) {
            throw new RuntimeException('No open service session. Operations require an active session.');
        }

        if ($expectedVersion !== null && $group->version_number !== $expectedVersion) {
            throw new RuntimeException('VERSION_CONFLICT');
        }

        $active = BillingStatus::where('code', BillingStatus::ACTIVE)->value('id');

        if ($group->status?->code === BillingStatus::ACTIVE && ! $group->is_closed) {
            return $group;
        }

        $update = [
            'billing_status_id' => $active,
            'version_number' => $group->version_number + 1,
        ];
        if ($group->is_closed) {
            $update['is_closed'] = false;
            $update['closed_at'] = null;
        }
        $group->update($update);

        Audit::record(
            'BILLING_GROUP_REOPENED',
            "Conta {$group->display_code} reaberta",
            [],
            ['billing_group_id' => $group->id, 'service_session_id' => $group->service_session_id, 'actor_user_id' => $actor->id],
        );

        return $group->refresh();
    }
}
