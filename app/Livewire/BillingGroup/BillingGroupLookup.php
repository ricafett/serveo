<?php

namespace App\Livewire\BillingGroup;

use App\Models\BillingGroup;
use App\Models\ServiceSession;
use Livewire\Attributes\Url;
use Livewire\Component;

class BillingGroupLookup extends Component
{
    #[Url(as: 'search')]
    public ?string $search = '';

    #[Url(as: 'showClosed')]
    public bool $showClosed = false;

    public function getGroupsProperty()
    {
        $session = ServiceSession::where('status', 'OPEN')->latest('starts_at')->first();
        if (! $session) {
            return collect();
        }

        $query = BillingGroup::with([
            'status',
            'occupiedZones' => fn ($q) => $q->where('is_open', true)->with('row.section'),
        ])
            ->where('service_session_id', $session->id);

        if (! $this->showClosed) {
            $query->where('is_closed', false);
        }

        if ($this->search) {
            $search = strtolower($this->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(display_code) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                  ->orWhereHas('occupiedZones.row.section', fn ($sq) => $sq->whereRaw('LOWER(section_code) LIKE ?', ["%{$search}%"]));
            });
        }

        return $query->orderBy('opened_at', 'desc')->get();
    }

    public function hasOpenSession(): bool
    {
        return ServiceSession::where('status', 'OPEN')->exists();
    }

    public function openCheckout(int $groupId): void
    {
        $this->redirect(route('checkout', ['id' => $groupId]), navigate: true);
    }

    public function render()
    {
        return view('livewire.billing-group.billing-group-lookup')
            ->layout('layouts.operational');
    }
}
