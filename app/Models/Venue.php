<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $fillable = ['venue_code', 'name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function serviceSessions(): HasMany
    {
        return $this->hasMany(ServiceSession::class);
    }

    public function printerRoutes(): HasMany
    {
        return $this->hasMany(PrinterRoute::class);
    }
}
