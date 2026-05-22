<?php

namespace App\Filament\Pages;

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Floor\ZoneOverlapException;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
use App\Models\Row;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Illuminate\Support\Facades\Auth;

/**
 * @deprecated Operational billing group detail UI has moved to Livewire at /billing-groups/{id}. This Filament page is kept for backward compatibility during transition.
 */
class BillingGroupDetail extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.billing-group-detail';

    protected static ?string $title = null;

    public static function getNavigationLabel(): string
    {
        return __('billing.group_title');
    }

    public function getTitle(): string
    {
        return __('billing.group_title').' '.$this->group?->display_code;
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'billing-groups-detail/{record}';
    }

    public ?int $record = null;

    public ?BillingGroup $group = null;

    public function mount(int $record): void
    {
        $this->record = $record;
        $this->group = BillingGroup::with([
            'status',
            'occupiedZones.row.section',
            'orderHeaders.items.menuItem',
            'paymentRecords',
            'billingDocuments',
        ])->findOrFail($record);
    }

    protected function refreshGroup(): void
    {
        $this->group = $this->group?->fresh([
            'status', 'occupiedZones.row.section',
            'orderHeaders.items.menuItem', 'paymentRecords', 'billingDocuments',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assignZone')
                ->label(__('billing.assign_zone'))
                ->icon('heroicon-o-rectangle-group')
                ->visible(fn () => ! $this->group?->is_closed && Auth::user()?->can('floor.assign_zone'))
                ->form([
                    Forms\Components\Select::make('row_id')
                        ->label(__('floor.row'))
                        ->options(function () {
                            return Row::with('section')->get()->mapWithKeys(fn ($r) => [
                                $r->id => $r->section->section_code.' · '.__('floor.row').' '.$r->row_code,
                            ])->all();
                        })
                        ->required(),
                    Forms\Components\TextInput::make('start')->label(__('billing.start_pair'))->numeric()->required()->minValue(1),
                    Forms\Components\TextInput::make('end')->label(__('billing.end_pair'))->numeric()->required()->minValue(1),
                    Forms\Components\TextInput::make('delivery_label')->label(__('billing.delivery_label'))->maxLength(100),
                ])
                ->action(function (array $data) {
                    $row = Row::findOrFail($data['row_id']);
                    try {
                        app(OccupancyService::class)->assignZone(
                            $this->group, $row, (int) $data['start'], (int) $data['end'],
                            Auth::user(), $data['delivery_label'] ?? null,
                        );
                        Notification::make()->title(__('billing.zone_assigned'))->success()->send();
                    } catch (ZoneOverlapException $e) {
                        Notification::make()->title(__('billing.zone_overlap'))->body($e->getMessage())->danger()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('billing.zone_error'))->body($e->getMessage())->danger()->send();
                    }
                    $this->refreshGroup();
                }),

            Action::make('addOrder')
                ->label(__('billing.new_order'))
                ->icon('heroicon-o-shopping-cart')
                ->color('primary')
                ->visible(fn () => ! $this->group?->is_closed && Auth::user()?->can('order.create'))
                ->url(fn () => OrderEntry::getUrl(['record' => $this->group->id])),

            Action::make('generateBill')
                ->label(__('billing.print_bill'))
                ->icon('heroicon-o-printer')
                ->color('warning')
                ->visible(fn () => ! $this->group?->is_closed && Auth::user()?->can('billing_document.create'))
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        app(BillingService::class)->generateInternalBill($this->group, Auth::user());
                        Notification::make()->title(__('billing.bill_sent'))->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('billing.bill_error'))->body($e->getMessage())->danger()->send();
                    }
                    $this->refreshGroup();
                }),

            Action::make('reopen')
                ->label(__('billing.reopen_group'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn () => $this->group?->is_closed && Auth::user()?->can('billing_group.reopen'))
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        app(BillingGroupService::class)->reopen($this->group, Auth::user());
                        Notification::make()->title(__('billing.group_reopened'))->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title(__('billing.reopen_error'))->body($e->getMessage())->danger()->send();
                    }
                    $this->refreshGroup();
                }),
        ];
    }

    public function releaseZone(int $zoneId): void
    {
        $zone = OccupiedZone::findOrFail($zoneId);
        if ($zone->billing_group_id !== $this->group->id) {
            return;
        }
        if (! Auth::user()?->can('floor.release_zone')) {
            Notification::make()->title(__('billing.zone_release_unauthorized'))->danger()->send();

            return;
        }
        try {
            app(OccupancyService::class)->releaseZone($zone, Auth::user());
            Notification::make()->title(__('billing.zone_released'))->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title(__('billing.zone_release_error'))->body($e->getMessage())->danger()->send();
        }
        $this->refreshGroup();
    }

    public function getViewData(): array
    {
        return [
            'group' => $this->group,
            'charges' => $this->group?->chargesTotal() ?? 0,
            'paid' => $this->group?->paymentsTotal() ?? 0,
            'balance' => $this->group?->balance() ?? 0,
        ];
    }
}
