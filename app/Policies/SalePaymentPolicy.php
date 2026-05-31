<?php

namespace App\Policies;

use App\Models\SalePayment;
use App\Models\User;

class SalePaymentPolicy
{
    public function record(User $user): bool
    {
        return $user->hasPermissionTo('sale_payment.record');
    }

    public function view(User $user, SalePayment $salePayment): bool
    {
        return $user->hasPermissionTo('sale.view');
    }
}
