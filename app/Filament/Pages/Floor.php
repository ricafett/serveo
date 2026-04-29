<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;

use App\Domain\Floor\BillingGroupService;
use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\OccupiedZone;
use App\Models\Section;
use App\Models\ServiceSession;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class Floor extends Page
{
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-squares-2x2';
    protected static string | UnitEnum | null $navigationGroup = 'Operação';
    protected static ?string $navigationLabel = 'Plano de sala';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.floor';
    protected static ?string $title = 'Plano de sala';

    public ?int $serviceSessionId = null;

    public function mount(): void
    {
        $session = ServiceSession::where('status', 'OPEN')->latest('starts_at')->first();
        $this->serviceSessionId = $session?->id;
    }

    public function getViewData(): array
    {
        $session = $this->serviceSessionId ? ServiceSession::find($this->serviceSessionId) : null;
        $sections = Section::with(['rows.seatPairs', 'rows.occupiedZones' => fn ($q) => $q->where('is_open', true)->with('billingGroup')])
            ->orderBy('sort_order')
            ->get();

        $openGroups = $session
            ? BillingGroup::with(['status', 'occupiedZones' => fn ($q) => $q->where('is_open', true)->with('row.section')])
                ->where('service_session_id', $session->id)
                ->where('is_closed', false)
                ->orderBy('opened_at', 'desc')
                ->get()
            : collect();

        return compact('session', 'sections', 'openGroups');
    }

    public function openGroup(): void
    {
        if (! $this->serviceSessionId) {
            Notification::make()->title('Sem sessão de serviço aberta.')->danger()->send();
            return;
        }
        $session = ServiceSession::findOrFail($this->serviceSessionId);
        $service = app(BillingGroupService::class);
        $group = $service->open($session, Auth::user());

        Notification::make()->title("Grupo {$group->display_code} aberto")->success()->send();
        $this->redirect(BillingGroupDetail::getUrl(['record' => $group->id]));
    }
}
