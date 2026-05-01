<?php

namespace App\Livewire\BillingGroup;

use Livewire\Component;

class BillingGroupLookup extends Component
{
    public function render()
    {
        return view('livewire.billing-group.billing-group-lookup')
            ->layout('layouts.operational');
    }
}
