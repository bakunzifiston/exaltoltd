<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One delivered line coming back, and what inspection found it to be. */
class StockReturnItem extends Model
{
    protected $fillable = [
        'sale_item_id', 'product_id', 'production_id', 'quantity_delivered', 'quantity_sold_reported',
        'quantity_returned', 'unit_price', 'line_value', 'quantity_restocked', 'quantity_damaged',
        'quantity_expired', 'quantity_rejected', 'condition_notes',
    ];

    protected $casts = [
        'quantity_delivered' => 'decimal:2', 'quantity_sold_reported' => 'decimal:2', 'quantity_returned' => 'decimal:2',
        'unit_price' => 'decimal:2', 'line_value' => 'decimal:2', 'quantity_restocked' => 'decimal:2',
        'quantity_damaged' => 'decimal:2', 'quantity_expired' => 'decimal:2', 'quantity_rejected' => 'decimal:2',
    ];

    public function stockReturn(): BelongsTo
    {
        return $this->belongsTo(StockReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    /** Units that came back but may not be sold again. */
    public function unsellableQuantity(): float
    {
        return round((float) $this->quantity_damaged + (float) $this->quantity_expired + (float) $this->quantity_rejected, 2);
    }
}
