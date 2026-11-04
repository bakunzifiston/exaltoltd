<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\InventoryRecord;
use App\Models\Production;
use Illuminate\Validation\Validator;

trait ChecksRawMaterialStock
{
    /**
     * Reject a production batch that would consume more raw material than is on hand.
     * On edit, the quantities this batch currently holds are added back first (they are
     * released and re-applied when the batch is saved).
     */
    protected function validateRawMaterialStock(Validator $validator, ?Production $production = null): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $requested = [];
        foreach ((array) $this->input('inventory_record_id', []) as $material) {
            $inventoryId = $material['inventory_id'] ?? null;
            if (! $inventoryId) {
                continue;
            }
            $requested[$inventoryId] = ($requested[$inventoryId] ?? 0) + (float) ($material['quantity_used'] ?? 0);
        }

        if ($requested === []) {
            return;
        }

        $held = [];
        if ($production) {
            foreach (Production::normalizeMaterials($production->inventory_record_id) as $material) {
                $inventoryId = $material['inventory_id'] ?? null;
                if (! $inventoryId) {
                    continue;
                }
                $held[$inventoryId] = ($held[$inventoryId] ?? 0) + (float) ($material['quantity_used'] ?? 0);
            }
        }

        $records = InventoryRecord::whereIn('id', array_keys($requested))->get()->keyBy('id');

        foreach ($requested as $inventoryId => $qty) {
            $record = $records[$inventoryId] ?? null;
            if (! $record) {
                continue;
            }

            // A delivery that failed inspection must never reach a batch, unless the batch
            // already used it before it was rejected.
            if ($record->inspection_status === 'rejected' && ! isset($held[$inventoryId])) {
                $validator->errors()->add('inventory_record_id', $record->item_name.' (delivery #'.$record->id.') failed inspection and cannot be used in production.');

                continue;
            }

            $available = $record->remainingQuantity() + (float) ($held[$inventoryId] ?? 0);

            if ($qty > $available + 0.0001) {
                $validator->errors()->add('inventory_record_id', sprintf(
                    'Not enough stock: only %s of %s is on hand, but %s were entered.',
                    $this->trimQty($available),
                    $record->item_name,
                    $this->trimQty($qty),
                ));
            }
        }
    }

    private function trimQty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
