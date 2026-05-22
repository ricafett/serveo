<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingStatus extends Model
{
    protected $fillable = ['code', 'display_name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public const WAITING = 'WAITING';

    public const ACTIVE = 'ACTIVE';

    public const CHECK_REQUESTED = 'CHECK_REQUESTED';

    public const PARTIALLY_PAID = 'PARTIALLY_PAID';

    public const CLOSED = 'CLOSED';
}
