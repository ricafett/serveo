<?php

namespace App\Domain;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

trait ChecksPermissions
{
    protected function ensureCan(User $user, string $permission): void
    {
        if (! $user->hasPermissionTo($permission)) {
            throw new AuthorizationException("Unauthorized: missing permission {$permission}");
        }
    }
}
