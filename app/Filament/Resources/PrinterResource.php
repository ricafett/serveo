<?php

namespace App\Filament\Resources;

use App\Domain\Printing\PrinterAdapterRegistry;
use App\Filament\Resources\PrinterResource\Pages;
use App\Models\Printer;
use BackedEnum;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class PrinterResource extends BaseResource
{
    protected static ?string $model = Printer::class;

    protected static string|UnitEnum|null $navigationGroup = 'app.navigation_group_config';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static ?string $navigationLabel = 'app.navigation_label_printers';

    protected static ?int $navigationSort = 40;

    public static function canViewAny(): bool
    {
        return Auth::user()?->can('printer.configure') ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->can('printer.configure') ?? false;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->can('printer.configure') ?? false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('printer.configure') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\Select::make('printer_type')->options([
                'KITCHEN' => __('app.printer_type_kitchen'),
                'BAR' => __('app.printer_type_bar'),
                'BILL' => __('app.printer_type_bill'),
                'GENERIC' => __('app.printer_type_generic'),
            ])->required(),
            Forms\Components\Select::make('connection_type')->options([
                'LAN' => __('app.connection_type_lan'),
                'USB_AGENT' => __('app.connection_type_usb_agent'),
                'NULL' => __('app.connection_type_null'),
            ])->required()->live(),
            Forms\Components\TextInput::make('address')->label(__('app.address_ip_hostname'))
                ->visible(fn (Get $get) => $get('connection_type') === 'LAN'),
            Forms\Components\TextInput::make('port')->numeric()->default(9100)
                ->visible(fn (Get $get) => $get('connection_type') === 'LAN'),
            Forms\Components\TextInput::make('agent_endpoint')->label(__('app.agent_url'))
                ->visible(fn (Get $get) => $get('connection_type') === 'USB_AGENT'),
            Forms\Components\TextInput::make('agent_printer_id')->label(__('app.agent_internal_id'))
                ->visible(fn (Get $get) => $get('connection_type') === 'USB_AGENT'),
            Forms\Components\Toggle::make('is_active')->default(true),
            Section::make(__('app.print_config_section'))
                ->schema([
                    Forms\Components\TextInput::make('print_char_width')
                        ->label(__('app.print_char_width'))
                        ->numeric()
                        ->minValue(20)
                        ->maxValue(64)
                        ->default(42)
                        ->helperText(__('app.print_char_width_help')),
                    Forms\Components\TextInput::make('print_begin_space')
                        ->label(__('app.print_begin_space'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(10)
                        ->default(0)
                        ->helperText(__('app.print_begin_space_help')),
                    Forms\Components\TextInput::make('print_end_space')
                        ->label(__('app.print_end_space'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(10)
                        ->default(3)
                        ->helperText(__('app.print_end_space_help')),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('printer_type')->badge(),
                Tables\Columns\TextColumn::make('connection_type')->badge(),
                Tables\Columns\TextColumn::make('address'),
                Tables\Columns\TextColumn::make('health_status')->badge()->colors([
                    'success' => 'OK',
                    'danger' => 'UNREACHABLE',
                    'warning' => ['WARNING', 'REACHABLE'],
                    'gray' => 'UNKNOWN',
                ])->label(__('app.health')),
                Tables\Columns\TextColumn::make('last_seen_at')->dateTime()->label(__('app.last_seen')),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\Action::make('testPrint')
                    ->label(__('app.test_print'))
                    ->icon('heroicon-o-play')
                    ->color('gray')
                    ->action(function (Printer $record) {
                        $registry = app(PrinterAdapterRegistry::class);

                        try {
                            $adapter = $registry->for($record);

                            // Test print payload: adapter handles init + cut.
                            // Includes Portuguese character set to verify encoding.
                            $testPayload = "=== Serveo Test Print ===\n"
                                ."Printer: {$record->name}\n"
                                ."Date: ".now()->format('Y-m-d H:i:s')."\n"
                                ."\n"
                                ."--- Portuguese characters ---\n"
                                ."Lower: à á â ã ä å æ ç è é ê ë ì í î ï ð ñ ò ó ô õ ö ø ù ú û ü\n"
                                ."Upper: À Á Â Ã Ä Å Æ Ç È É Ê Ë Ì Í Î Ï Ð Ñ Ò Ó Ô Õ Ö Ø Ù Ú Û Ü\n"
                                ."PT sp: ç Ç ã Ã õ Õ á Á é É í Í ó Ó ú Ú â Â ê Ê ô Ô à À\n"
                                ."\n"
                                ."--- Special characters ---\n"
                                ."EUR (Euro)\n"
                                ."\n"
                                ."--- Character map ---\n"
                                ."0123456789\n"
                                .'!"#$%&\'()*+,-./:;<=>?@'."\n"
                                ."[\\]^_`{|}~\n";

                            $result = $adapter->send($record, $testPayload);

                            if ($result->success) {
                                $record->update([
                                    'health_status' => 'OK',
                                    'last_seen_at' => now(),
                                    'last_error' => null,
                                ]);

                                Notification::make()
                                    ->title(__('app.test_print'))
                                    ->body(__('app.test_print_sent').' — '.$result->message)
                                    ->success()
                                    ->send();
                            } else {
                                $record->update([
                                    'health_status' => 'UNREACHABLE',
                                    'last_error' => $result->message,
                                ]);

                                Notification::make()
                                    ->title(__('app.test_print'))
                                    ->body(__('app.test_print_failed').': '.$result->message)
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title(__('app.test_print'))
                                ->body(__('app.test_print_failed').': '.$e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrinters::route('/'),
            'create' => Pages\CreatePrinter::route('/create'),
            'edit' => Pages\EditPrinter::route('/{record}/edit'),
        ];
    }
}
