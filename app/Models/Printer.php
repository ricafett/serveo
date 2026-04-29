<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Printer extends Model
{
    protected $fillable = [
        'name', 'printer_type', 'connection_type',
        'address', 'port', 'agent_endpoint', 'agent_printer_id',
        'is_active', 'health_status', 'last_seen_at', 'last_error',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public const TYPE_KITCHEN = 'KITCHEN';
    public const TYPE_BAR     = 'BAR';
    public const TYPE_BILL    = 'BILL';

    public const CONN_LAN       = 'LAN';
    public const CONN_USB_AGENT = 'USB_AGENT';
    public const CONN_NULL      = 'NULL';

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }
}
