<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_header_id', 'menu_item_id', 'quantity',
        'unit_price', 'line_subtotal', 'fulfillment_route',
        'delivery_seat_pair_id', 'delivery_reference_label',
        'sent_to_production_at', 'voided_at', 'voided_by_user_id',
        'void_reason', 'parent_order_item_id',
    ];

    protected $casts = [
        'unit_price'            => 'decimal:2',
        'line_subtotal'         => 'decimal:2',
        'sent_to_production_at' => 'datetime',
        'voided_at'             => 'datetime',
    ];

    public function header(): BelongsTo
    {
        return $this->belongsTo(OrderHeader::class, 'order_header_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function deliverySeatPair(): BelongsTo
    {
        return $this->belongsTo(SeatPair::class, 'delivery_seat_pair_id');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
