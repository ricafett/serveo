<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingExport extends Model
{
    protected $fillable = [
        'venue_id', 'service_session_id', 'export_type', 'source_domain',
        'export_range_start', 'export_range_end',
        'file_name', 'file_format', 'export_status',
        'requested_by_user_id', 'requested_at', 'completed_at',
    ];

    protected $casts = [
        'export_range_start' => 'datetime',
        'export_range_end' => 'datetime',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function serviceSession(): BelongsTo
    {
        return $this->belongsTo(ServiceSession::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
