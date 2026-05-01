<?php

namespace App\Livewire\Cashier;

use Livewire\Component;

class ReprintPanel extends Component
{
    public ?int $billingGroupId = null;

    public function mount(?int $billingGroupId = null): void
    {
        $this->billingGroupId = $billingGroupId;
    }

    public function render()
    {
        return view('livewire.cashier.reprint-panel')
            ->layout('layouts.operational');
    }
}
