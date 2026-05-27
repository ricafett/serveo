<?php

namespace App\Filament\Resources\PrinterResource\Pages;

use App\Filament\Resources\PrinterResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class ListPrinters extends ListRecords
{
    protected static string $resource = PrinterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('runHealthCheck')
                ->label(__('app.health_check_run'))
                ->icon('heroicon-o-heart')
                ->color('gray')
                ->action(function () {
                    // Clear the mutex so the command always runs when triggered manually
                    Cache::forget('health_check_printers_last_run');

                    Artisan::call('serveo:health-check-printers', ['--force' => true]);
                    $output = Artisan::output();

                    Notification::make()
                        ->title(__('app.health_check_complete'))
                        ->body(trim(strip_tags($output)))
                        ->success()
                        ->send();

                    // Refresh the table to show updated health statuses
                    $this->dispatch('$refresh');
                }),
        ];
    }
}
