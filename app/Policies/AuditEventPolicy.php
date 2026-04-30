<?php

namespace App\Policies;

use App\Models\AuditEvent;
use App\Models\User;

class AuditEventPolicy
{
    public function view(User $user): bool
    {
        return $user->hasPermissionTo('event_log.view_limited');
    }

    public function viewFull(User $user): bool
    {
        return $user->hasPermissionTo('event_log.view_full');
    }
}
