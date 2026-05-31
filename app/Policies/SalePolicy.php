<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('sale.create');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->hasPermissionTo('sale.view');
    }

    public function print(User $user, Sale $sale): bool
    {
        return $user->hasPermissionTo('sale.print');
    }

    public function receipt(User $user, Sale $sale): bool
    {
        return $user->hasPermissionTo('sale.receipt');
    }
}
