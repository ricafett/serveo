<?php

namespace App\Policies;

use App\Models\OrderHeader;
use App\Models\User;

class OrderPolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('order.create');
    }

    public function view(User $user, OrderHeader $orderHeader): bool
    {
        return $user->hasPermissionTo('order.create') || $user->hasPermissionTo('billing_group.view');
    }

    public function voidItem(User $user, OrderHeader $orderHeader): bool
    {
        return $user->hasPermissionTo('order.void_item');
    }
}
