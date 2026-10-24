<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnItem extends Model
{
    protected $fillable = ['order_item_id', 'quantity', 'condition', 'unit_refund', 'refund_amount'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_refund' => 'decimal:2', 'refund_amount' => 'decimal:2'];
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(OrderReturn::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
