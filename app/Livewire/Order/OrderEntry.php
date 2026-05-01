<?php

namespace App\Livewire\Order;

use Livewire\Component;

class OrderEntry extends Component
{
    public int $billingGroupId;

    public function mount(int $billingGroupId): void
    {
        $this->billingGroupId = $billingGroupId;
    }

    public function render()
    {
        return view('livewire.order.order-entry')
            ->layout('layouts.operational');
    }
}
