<?php

namespace App\Livewire\Cashier;

use Livewire\Component;

class Checkout extends Component
{
    public int $id;

    public function mount(int $id): void
    {
        $this->id = $id;
    }

    public function render()
    {
        return view('livewire.cashier.checkout')
            ->layout('layouts.operational');
    }
}
