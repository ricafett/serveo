<?php

namespace App\Policies;

use App\Models\OrderHeader;
use App\Models\User;

class OrderPolicy
{
    private function canVoidOrder(User $user, OrderHeader $orderHeader): bool
    {
        if (! $user->hasPermissionTo('order.void_item')) {
            return false;
        }

        if ($user->hasRole('ADMIN') || $user->hasRole('CASHIER')) {
            return true;
        }

        if ($user->hasRole('SERVER')) {
            return $orderHeader->ordered_by_user_id === $user->id;
        }

        return false;
    }

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
        return $this->canVoidOrder($user, $orderHeader);
    }

    public function voidOrder(User $user, OrderHeader $orderHeader): bool
    {
        return $this->canVoidOrder($user, $orderHeader);
    }
}
