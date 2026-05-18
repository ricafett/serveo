<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

abstract class BasePage extends Page
{
    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return static::$navigationGroup ? __(static::$navigationGroup) : parent::getNavigationGroup();
    }
}
