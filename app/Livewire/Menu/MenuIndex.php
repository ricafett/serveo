<?php

namespace App\Livewire\Menu;

use App\Models\MenuCategory;
use Livewire\Component;

class MenuIndex extends Component
{
    public function render()
    {
        $categories = MenuCategory::with([
            'items' => fn ($q) => $q->where('is_active', true)->orderBy('display_name'),
            'items.variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
            'items.modifierSet' => fn ($q) => $q->where('is_active', true),
            'items.modifierSet.items' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('livewire.menu.menu-index', [
            'categories' => $categories,
        ])->layout('layouts.operational');
    }
}
