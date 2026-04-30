<?php

namespace App\Policies;

use App\Models\BillingGroup;
use App\Models\User;

class BillingGroupPolicy
{
    public function view(User $user, BillingGroup $billingGroup): bool
    {
        return $user->hasPermissionTo('billing_group.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('floor.open_billing_group');
    }

    public function updateStatus(User $user, BillingGroup $billingGroup): bool
    {
        return $user->hasPermissionTo('billing_group.set_status');
    }

    public function reopen(User $user, BillingGroup $billingGroup): bool
    {
        return $user->hasPermissionTo('billing_group.reopen');
    }

    public function close(User $user, BillingGroup $billingGroup): bool
    {
        return $user->hasPermissionTo('billing_group.set_status');
    }

    public function assignZone(User $user, BillingGroup $billingGroup): bool
    {
        return $user->hasPermissionTo('floor.assign_zone');
    }

    public function releaseZone(User $user, BillingGroup $billingGroup): bool
    {
        return $user->hasPermissionTo('floor.release_zone');
    }
}
