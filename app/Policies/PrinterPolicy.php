<?php

namespace App\Policies;

use App\Models\Printer;
use App\Models\User;

class PrinterPolicy
{
    public function configure(User $user): bool
    {
        return $user->hasPermissionTo('printer.configure');
    }

    public function test(User $user, Printer $printer): bool
    {
        return $user->hasPermissionTo('printer.test');
    }

    public function routeChange(User $user): bool
    {
        return $user->hasPermissionTo('printer.route_change');
    }
}
