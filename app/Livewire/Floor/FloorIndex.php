<?php

namespace App\Livewire\Floor;

use Livewire\Component;

class FloorIndex extends Component
{
    public function render()
    {
        return view('livewire.floor.floor-index')
            ->layout('layouts.operational');
    }
}
