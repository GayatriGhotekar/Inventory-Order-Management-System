<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['product_id', 'quantity', 'received_quantity', 'unit_cost', 'total_cost'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'received_quantity' => 'integer', 'unit_cost' => 'decimal:2', 'total_cost' => 'decimal:2'];
    }

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function receiptItems(): HasMany { return $this->hasMany(GoodsReceiptItem::class); }
}
