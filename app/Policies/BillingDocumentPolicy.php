<?php

namespace App\Policies;

use App\Models\BillingDocument;
use App\Models\User;

class BillingDocumentPolicy
{
    public function print(User $user): bool
    {
        return $user->hasPermissionTo('billing_document.create');
    }

    public function reprint(User $user, BillingDocument $billingDocument): bool
    {
        return $user->hasPermissionTo('billing_document.reprint');
    }

    public function view(User $user, BillingDocument $billingDocument): bool
    {
        return $user->hasPermissionTo('billing_group.view');
    }
}
