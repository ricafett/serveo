<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Application user. Mapped 1:1 with the AppUser entity from the data model.
 * Note: We keep `users` as the table name (Laravel convention) but treat this
 * as the canonical AppUser. The user_id column is therefore the AppUserId.
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public const THEME_LIGHT  = 'light';
    public const THEME_DARK   = 'dark';
    public const THEME_SYSTEM = 'system';

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'preferred_language_code',
        'theme',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
        ];
    }

    /**
     * Only ADMIN may sign in to the Filament admin panel.
     * CASHIER and SERVER will use the operational UI when built.
     * KITCHEN_OUTPUT/BAR_OUTPUT are non-interactive.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        return $this->hasRole('ADMIN');
    }
}
