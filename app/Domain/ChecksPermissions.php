<?php

namespace App\Domain;

use App\Models\User;
use RuntimeException;

trait ChecksPermissions
{
    protected function ensureCan(User $user, string $permission): void
    {
        if (! $user->hasPermissionTo($permission)) {
            throw new RuntimeException("Unauthorized: missing permission {$permission}");
        }
    }
}
