<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TranslationKey extends Model
{
    protected $fillable = [
        'language_code', 'translation_namespace', 'translation_key',
        'translation_value', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];
}
