<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Printer extends Model
{
    protected $fillable = [
        'name', 'connection_type',
        'address', 'port', 'agent_endpoint', 'agent_printer_id',
        'is_active', 'health_status', 'last_seen_at', 'last_error',
        'print_char_width', 'print_begin_space', 'print_end_space',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'print_char_width' => 'integer',
        'print_begin_space' => 'integer',
        'print_end_space' => 'integer',
    ];

    public const CONN_LAN = 'LAN';

    public const CONN_USB_AGENT = 'USB_AGENT';

    public const CONN_NULL = 'NULL';

    public function printJobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function printerRoutes(): HasMany
    {
        return $this->hasMany(PrinterRoute::class);
    }
}
