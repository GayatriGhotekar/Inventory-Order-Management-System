<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    protected $fillable = ['purchase_order_item_id', 'accepted_quantity', 'damaged_quantity', 'rejected_quantity'];

    protected function casts(): array
    {
        return ['accepted_quantity' => 'integer', 'damaged_quantity' => 'integer', 'rejected_quantity' => 'integer'];
    }

    public function receipt(): BelongsTo { return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id'); }
    public function purchaseOrderItem(): BelongsTo { return $this->belongsTo(PurchaseOrderItem::class); }
}
