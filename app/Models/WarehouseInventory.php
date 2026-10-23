<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseInventory extends Model
{
    protected $fillable = ['warehouse_id', 'product_id', 'on_hand_quantity', 'reserved_quantity', 'damaged_quantity', 'reorder_level'];

    protected $appends = ['available_quantity'];

    protected function casts(): array
    {
        return [
            'on_hand_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'damaged_quantity' => 'integer',
            'reorder_level' => 'integer',
        ];
    }

    public function getAvailableQuantityAttribute(): int
    {
        return max(0, $this->on_hand_quantity - $this->reserved_quantity - $this->damaged_quantity);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
