<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'production_id',
        'quantity_sold',
        'quantity_returned',
        'unit_price',
        'line_total',
        'unit_cost',
        'line_cogs',
    ];

    protected $casts = [
        'quantity_sold' => 'decimal:2',
        'quantity_returned' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'line_cogs' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (SaleItem $item) {
            // Row-lock the batch, then assert availability before deducting. No silent
            // clamping to zero — if stock is insufficient the whole sale transaction rolls back.
            $stock = app(\App\Services\StockService::class);
            $production = $stock->lockFinishedGoods((int) $item->production_id);

            if ($production) {
                $stock->assertFinishedGoods($production, (float) $item->quantity_sold);

                // quantity_produced stays immutable (historical batch size);
                // only the sellable remainder is reduced.
                $production->quantity_remaining = round((float) $production->quantity_remaining - (float) $item->quantity_sold, 2);
                $production->saveQuietly();

                StockMovement::record(
                    type: 'sold',
                    quantity: -1 * (float) $item->quantity_sold,
                    productId: $item->product_id,
                    productionId: $item->production_id,
                    referenceType: 'sale_item',
                    referenceId: $item->id,
                );
            }
        });
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }
}
