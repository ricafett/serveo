<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ? __(static::$navigationLabel) : parent::getNavigationLabel();
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return static::$navigationGroup ? __(static::$navigationGroup) : parent::getNavigationGroup();
    }

    public static function getModelLabel(): string
    {
        return static::$modelLabel ? __(static::$modelLabel) : parent::getModelLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::$pluralModelLabel ? __(static::$pluralModelLabel) : parent::getPluralModelLabel();
    }
}
