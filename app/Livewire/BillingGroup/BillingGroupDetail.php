<?php

namespace App\Livewire\BillingGroup;

use Livewire\Component;

class BillingGroupDetail extends Component
{
    public int $id;

    public function mount(int $id): void
    {
        $this->id = $id;
    }

    public function render()
    {
        return view('livewire.billing-group.billing-group-detail')
            ->layout('layouts.operational');
    }
}
