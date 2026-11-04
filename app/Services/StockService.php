<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryRecord;
use App\Models\Production;

/**
 * Single source of truth for "how much can we actually use/sell right now".
 * Used as the last line of defence during the real deduction (validation guards
 * the form; this guards the write — including under concurrency, via row locks).
 */
class StockService
{
    /** Finished-goods units still available on a production batch (row-locked for the caller's transaction). */
    public function lockFinishedGoods(int $productionId): ?Production
    {
        return Production::query()->whereKey($productionId)->lockForUpdate()->first();
    }

    /** Raw-material units still on hand for an inventory lot (row-locked). */
    public function lockRawMaterial(int $inventoryRecordId): ?InventoryRecord
    {
        return InventoryRecord::query()->whereKey($inventoryRecordId)->lockForUpdate()->first();
    }

    public function assertFinishedGoods(Production $production, float $requested): void
    {
        $available = (float) $production->quantity_remaining;
        if ($requested > $available + 0.0001) {
            throw new InsufficientStockException(
                $this->productLabel($production),
                $requested,
                max($available, 0),
            );
        }
    }

    public function assertRawMaterial(InventoryRecord $record, float $requested): void
    {
        $available = $record->remainingQuantity();
        if ($requested > $available + 0.0001) {
            throw new InsufficientStockException($record->item_name, $requested, $available);
        }
    }

    private function productLabel(Production $production): string
    {
        $product = $production->product?->type;

        return $product
            ? sprintf('%s (batch %s)', $product, $production->batch_id)
            : sprintf('batch %s', $production->batch_id);
    }
}
