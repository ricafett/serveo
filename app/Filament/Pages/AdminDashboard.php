<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Dashboard;

class AdminDashboard extends Dashboard
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $title = 'Admin Dashboard';

    public static function getNavigationLabel(): string
    {
        return __('app.navigation_label_admin_dashboard');
    }
}
