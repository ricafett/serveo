<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;

use App\Domain\Billing\BillingService;
use App\Domain\Floor\BillingGroupService;
use App\Models\BillingDocument;
use App\Models\BillingGroup;
use App\Models\PaymentRecord;
use App\Models\ServiceSession;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class CashierCheckout extends Page
{
    protected static string | BackedEnum | null $navigationIcon  = 'heroicon-o-banknotes';
    protected static string | UnitEnum | null $navigationGroup = 'Operação';
    protected static ?string $navigationLabel = 'Caixa';
    protected static ?int    $navigationSort  = 2;
    protected string $view = 'filament.pages.cashier-checkout';
    protected static ?string $title           = 'Caixa';

    public ?int $serviceSessionId = null;
    public bool $showClosed = false;

    public function mount(): void
    {
        $session = ServiceSession::where('status', 'OPEN')->latest('starts_at')->first();
        $this->serviceSessionId = $session?->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->recordPaymentAction(),
            Action::make('toggleClosed')
                ->label(fn () => $this->showClosed ? 'Ocultar fechados' : 'Mostrar fechados')
                ->icon('heroicon-o-eye')
                ->action(fn () => $this->showClosed = ! $this->showClosed),
        ];
    }

    public function generateBill(int $groupId): void
    {
        $group = BillingGroup::findOrFail($groupId);
        try {
            app(BillingService::class)->generateInternalBill($group, Auth::user());
            Notification::make()->title('Conta enviada para impressora')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Erro')->body($e->getMessage())->danger()->send();
        }
    }

    public function reprintLastBill(int $groupId): void
    {
        $bill = BillingDocument::where('billing_group_id', $groupId)
            ->where('document_type', BillingDocument::TYPE_INTERNAL_BILL)
            ->latest('id')->first();

        if (! $bill) {
            Notification::make()->title('Sem conta para reimprimir')->warning()->send();
            return;
        }
        try {
            app(BillingService::class)->reprintBill($bill, Auth::user());
            Notification::make()->title('Reimpressão enviada')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Erro')->body($e->getMessage())->danger()->send();
        }
    }

    public function reopenGroup(int $groupId): void
    {
        $group = BillingGroup::findOrFail($groupId);
        app(BillingGroupService::class)->reopen($group, Auth::user());
        Notification::make()->title('Grupo reaberto')->success()->send();
    }

    public function recordPaymentAction(): Action
    {
        return Action::make('recordPayment')
            ->label('Registar pagamento')
            ->icon('heroicon-o-currency-euro')
            ->color('success')
            ->form([
                Forms\Components\Select::make('group_id')
                    ->label('Grupo')
                    ->options(fn () => $this->openGroups()->mapWithKeys(fn ($g) => [
                        $g->id => $g->display_code.' — saldo '.number_format($g->balance(), 2, ',', ' ').' EUR',
                    ])->all())
                    ->required(),
                Forms\Components\TextInput::make('amount')->label('Valor (EUR)')->numeric()->required()->minValue(0.01),
                Forms\Components\TextInput::make('label')->label('Forma de pagamento')->required()->default('Numerário'),
                Forms\Components\Textarea::make('notes')->rows(2),
            ])
            ->action(function (array $data) {
                $group = BillingGroup::findOrFail($data['group_id']);
                app(BillingService::class)->recordPayment(
                    $group, Auth::user(),
                    (float) $data['amount'], $data['label'], $data['notes'] ?? null,
                );
                Notification::make()->title('Pagamento registado')->success()->send();
            });
    }

    protected function openGroups()
    {
        return BillingGroup::with(['status', 'occupiedZones.row.section'])
            ->where('service_session_id', $this->serviceSessionId)
            ->where('is_closed', false)
            ->orderBy('opened_at')
            ->get();
    }

    public function getViewData(): array
    {
        $session = $this->serviceSessionId ? ServiceSession::find($this->serviceSessionId) : null;
        $query = BillingGroup::with(['status', 'occupiedZones.row.section'])
            ->where('service_session_id', $this->serviceSessionId)
            ->orderBy('opened_at', 'desc');

        if (! $this->showClosed) {
            $query->where('is_closed', false);
        }

        return [
            'session' => $session,
            'groups'  => $query->get(),
        ];
    }
}
