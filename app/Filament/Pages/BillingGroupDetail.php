<?php

namespace App\Filament\Pages;

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Domain\Floor\OccupancyService;
use App\Domain\Floor\ZoneOverlapException;
use App\Models\BillingGroup;
use App\Models\OccupiedZone;
use App\Models\Row;
use App\Models\Section;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class BillingGroupDetail extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.billing-group-detail';
    protected static ?string $title = 'Grupo';

    public static function getSlug(): string
    {
        return 'billing-groups/{record}';
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

    public function getTitle(): string
    {
        return 'Grupo '.$this->group?->display_code;
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
                ->label('Atribuir zona')
                ->icon('heroicon-o-rectangle-group')
                ->visible(fn () => ! $this->group?->is_closed)
                ->form([
                    Forms\Components\Select::make('row_id')
                        ->label('Fila')
                        ->options(function () {
                            return Row::with('section')->get()->mapWithKeys(fn ($r) => [
                                $r->id => $r->section->section_code.' · Fila '.$r->row_code,
                            ])->all();
                        })
                        ->required(),
                    Forms\Components\TextInput::make('start')->label('Par inicial')->numeric()->required()->minValue(1),
                    Forms\Components\TextInput::make('end')->label('Par final')->numeric()->required()->minValue(1),
                    Forms\Components\TextInput::make('delivery_label')->label('Etiqueta entrega')->maxLength(100),
                ])
                ->action(function (array $data) {
                    $row = Row::findOrFail($data['row_id']);
                    try {
                        app(OccupancyService::class)->assignZone(
                            $this->group, $row, (int) $data['start'], (int) $data['end'],
                            Auth::user(), $data['delivery_label'] ?? null,
                        );
                        Notification::make()->title('Zona atribuída')->success()->send();
                    } catch (ZoneOverlapException $e) {
                        Notification::make()->title('Sobreposição de zonas')->body($e->getMessage())->danger()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Erro ao atribuir zona')->body($e->getMessage())->danger()->send();
                    }
                    $this->refreshGroup();
                }),

            Action::make('addOrder')
                ->label('Novo pedido')
                ->icon('heroicon-o-shopping-cart')
                ->color('primary')
                ->visible(fn () => ! $this->group?->is_closed)
                ->url(fn () => OrderEntry::getUrl(['record' => $this->group->id])),

            Action::make('generateBill')
                ->label('Imprimir conta')
                ->icon('heroicon-o-printer')
                ->color('warning')
                ->visible(fn () => ! $this->group?->is_closed)
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        app(BillingService::class)->generateInternalBill($this->group, Auth::user());
                        Notification::make()->title('Conta enviada para impressão')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Erro')->body($e->getMessage())->danger()->send();
                    }
                    $this->refreshGroup();
                }),

            Action::make('reopen')
                ->label('Reabrir grupo')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn () => $this->group?->is_closed)
                ->requiresConfirmation()
                ->action(function () {
                    app(BillingGroupService::class)->reopen($this->group, Auth::user());
                    Notification::make()->title('Grupo reaberto')->success()->send();
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
        app(OccupancyService::class)->releaseZone($zone, Auth::user());
        Notification::make()->title('Zona libertada')->success()->send();
        $this->refreshGroup();
    }

    public function getViewData(): array
    {
        return [
            'group'    => $this->group,
            'charges'  => $this->group?->chargesTotal() ?? 0,
            'paid'     => $this->group?->paymentsTotal() ?? 0,
            'balance'  => $this->group?->balance() ?? 0,
        ];
    }
}
