<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Simple key-value settings store used for seed version tracking
 * and other lightweight configuration that doesn't warrant its
 * own dedicated table.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Retrieve a setting value with a default fallback.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $record = static::find($key);

        return $record?->value ?? $default;
    }

    /**
     * Store (or update) a setting value.
     */
    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );
    }
}
