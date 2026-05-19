<?php

namespace App\Livewire\Home;

use App\Models\ServiceSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public ?ServiceSession $activeSession = null;

    public array $tiles = [];

    public function mount(): void
    {
        $this->activeSession = ServiceSession::where('status', 'OPEN')
            ->latest('starts_at')
            ->first();

        $this->tiles = $this->buildTiles();
    }

    private function buildTiles(): array
    {
        $user = Auth::user();
        $hasSession = $this->activeSession !== null;
        $tiles = [];

        if ($hasSession && ($user?->hasRole('SERVER') || $user?->hasRole('ADMIN'))) {
            $tiles[] = [
                'route' => 'floor',
                'label' => __('dashboard.floor_tile'),
                'description' => __('dashboard.floor_description'),
                'icon' => $this->floorIcon(),
                'color' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300',
            ];
        }

        if ($hasSession && ($user?->hasRole('CASHIER') || $user?->hasRole('ADMIN'))) {
            $tiles[] = [
                'route' => 'lookup',
                'label' => __('dashboard.lookup_tile'),
                'description' => __('dashboard.lookup_description'),
                'icon' => $this->lookupIcon(),
                'color' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300',
            ];

            $tiles[] = [
                'route' => 'reprint',
                'label' => __('dashboard.reprint_tile'),
                'description' => __('dashboard.reprint_description'),
                'icon' => $this->reprintIcon(),
                'color' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-300',
            ];
        }

        if ($user?->hasRole('ADMIN')) {
            $tiles[] = [
                'route' => 'filament.admin.pages.dashboard',
                'label' => __('dashboard.admin_panel_tile'),
                'description' => __('dashboard.admin_panel_description'),
                'icon' => $this->adminIcon(),
                'color' => 'bg-purple-50 text-purple-700 dark:bg-purple-900/20 dark:text-purple-300',
            ];
        }

        return $tiles;
    }

    private function floorIcon(): string
    {
        return '<svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>';
    }

    private function lookupIcon(): string
    {
        return '<svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>';
    }

    private function reprintIcon(): string
    {
        return '<svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0" /></svg>';
    }

    private function adminIcon(): string
    {
        return '<svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 011.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.56.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.893.149c-.425.07-.765.383-.93.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 01-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.397.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 01-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.505-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.107-1.204l-.527-.738a1.125 1.125 0 01.12-1.45l.773-.773a1.125 1.125 0 011.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>';
    }

    public function render()
    {
        return view('livewire.home.dashboard')
            ->layout('layouts.operational');
    }
}
