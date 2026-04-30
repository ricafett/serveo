<?php

namespace App\Policies;

use App\Models\PaymentRecord;
use App\Models\User;

class PaymentRecordPolicy
{
    public function record(User $user): bool
    {
        return $user->hasPermissionTo('payment.record');
    }

    public function void(User $user, PaymentRecord $paymentRecord): bool
    {
        return $user->hasPermissionTo('payment.void');
    }

    public function view(User $user, PaymentRecord $paymentRecord): bool
    {
        return $user->hasPermissionTo('billing_group.view');
    }
}
