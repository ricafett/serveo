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
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

/**
 * @deprecated Operational floor UI has moved to Livewire at /floor. This Filament page is kept for backward compatibility during transition.
 */
class Floor extends BasePage
{
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-squares-2x2';
    protected static string | UnitEnum | null $navigationGroup = 'app.navigation_group_operation';
    protected static ?string $navigationLabel = null;
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.floor';
    protected static ?string $title = null;

    public static function getNavigationLabel(): string
    {
        return __('floor.title');
    }

    public function getTitle(): string
    {
        return __('floor.title');
    }

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
        if (! Auth::user()?->can('floor.open_billing_group')) {
            Notification::make()->title(__('floor.unauthorized'))->danger()->send();
            return;
        }
        if (! $this->serviceSessionId) {
            Notification::make()->title(__('floor.no_session'))->danger()->send();
            return;
        }
        $session = ServiceSession::findOrFail($this->serviceSessionId);
        $service = app(BillingGroupService::class);
        $group = $service->open($session, Auth::user());

        Notification::make()->title("{$group->display_code} " . __('app.open'))->success()->send();
        $this->redirect(BillingGroupDetail::getUrl(['record' => $group->id]));
    }
}
