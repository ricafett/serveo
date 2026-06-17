<?php

namespace App\Filament\Pages;

use App\Domain\Audit\Audit;
use App\Domain\Printing\PrintQueueService;
use App\Domain\Session\SessionTotalsService;
use App\Models\CashierPrinterAssignment;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\ServiceSession;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class SessionTotals extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected string $view = 'filament.pages.session-totals';

    protected static ?string $navigationLabel = 'app.navigation_label_session_totals';

    protected static ?string $title = 'app.page_title_session_totals';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): string
    {
        return __('app.navigation_group_operation');
    }

    public ?ServiceSession $session = null;

    public Collection $cashiers;

    public array $summary = [];

    public Collection $inventory;

    public Collection $recentDocuments;

    public static function canAccess(): bool
    {
        return Auth::user()?->can('session_totals.view') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __(static::$navigationLabel);
    }

    public function getTitle(): string
    {
        return __(static::$title);
    }

    public function mount(SessionTotalsService $totalsService): void
    {
        $this->session = ServiceSession::where('status', 'OPEN')->first();

        if (! $this->session) {
            $this->cashiers = collect();
            $this->summary = [];
            $this->inventory = collect();
            $this->recentDocuments = collect();

            return;
        }

        $this->cashiers = $totalsService->computeCashierTotals($this->session);
        $this->summary = $totalsService->computeSummary($this->session);
        $this->inventory = $totalsService->computeInventoryMovements($this->session);

        $this->recentDocuments = PrintJob::whereIn('job_kind', [
            PrintJob::KIND_SESSION_TOTALS,
            PrintJob::KIND_INVENTORY_MOVEMENTS,
        ])
            ->with('printer')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }

    protected function getHeaderActions(): array
    {
        if (! $this->session) {
            return [];
        }

        return [
            Action::make('printSessionTotals')
                ->label(__('app.print_session_totals'))
                ->icon('heroicon-o-printer')
                ->color('primary')
                ->action(function (PrintQueueService $printQueue) {
                    $printer = $this->resolvePrinter();
                    if (! $printer) {
                        Notification::make()
                            ->title(__('app.no_printer_assigned'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $data = [
                        'session_label' => $this->session->session_label . ' (' . $this->session->starts_at?->format('Y-m-d H:i') . ')',
                        'cashiers' => $this->cashiers->toArray(),
                        'summary' => $this->summary,
                    ];

                    $printJob = $printQueue->enqueueSessionTotals($printer->id, $data, Auth::user());

                    Audit::record(
                        'SESSION_TOTALS_REQUESTED',
                        'Session totals print requested',
                        ['printer_id' => $printer->id, 'print_job_id' => $printJob->id],
                        ['actor_user_id' => Auth::id(), 'service_session_id' => $this->session->id],
                    );

                    Notification::make()
                        ->title(__('app.print_queued'))
                        ->success()
                        ->send();

                    $this->refreshRecentDocuments();
                }),

            Action::make('printInventoryMovements')
                ->label(__('app.print_inventory_movements'))
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->action(function (PrintQueueService $printQueue) {
                    $printer = $this->resolvePrinter();
                    if (! $printer) {
                        Notification::make()
                            ->title(__('app.no_printer_assigned'))
                            ->danger()
                            ->send();

                        return;
                    }

                    $data = [
                        'session_label' => $this->session->session_label . ' (' . $this->session->starts_at?->format('Y-m-d H:i') . ')',
                        'items' => $this->inventory->toArray(),
                    ];

                    $printJob = $printQueue->enqueueInventoryMovements($printer->id, $data, Auth::user());

                    Audit::record(
                        'INVENTORY_MOVEMENTS_REQUESTED',
                        'Inventory movements print requested',
                        ['printer_id' => $printer->id, 'print_job_id' => $printJob->id],
                        ['actor_user_id' => Auth::id(), 'service_session_id' => $this->session->id],
                    );

                    Notification::make()
                        ->title(__('app.print_queued'))
                        ->success()
                        ->send();

                    $this->refreshRecentDocuments();
                }),
        ];
    }

    private function resolvePrinter(): ?Printer
    {
        $assignment = CashierPrinterAssignment::with('printer')
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->first();

        return $assignment?->printer;
    }

    private function refreshRecentDocuments(): void
    {
        $this->recentDocuments = PrintJob::whereIn('job_kind', [
            PrintJob::KIND_SESSION_TOTALS,
            PrintJob::KIND_INVENTORY_MOVEMENTS,
        ])
            ->with('printer')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
    }
}
