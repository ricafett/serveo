<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_header_id', 'menu_item_id', 'quantity',
        'unit_price', 'line_subtotal', 'fulfillment_route',
        'delivery_seat_pair_id', 'delivery_reference_label',
        'sent_to_production_at', 'voided_at', 'voided_by_user_id',
        'void_reason', 'parent_order_item_id', 'variant_name', 'modifier_name',
        'note', 'delivered_at', 'delivered_by_user_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'sent_to_production_at' => 'datetime',
        'voided_at' => 'datetime',
        'delivered_at' => 'datetime',
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

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }

    public function productionTickets(): BelongsToMany
    {
        return $this->belongsToMany(ProductionTicket::class, 'production_ticket_items');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }
}
