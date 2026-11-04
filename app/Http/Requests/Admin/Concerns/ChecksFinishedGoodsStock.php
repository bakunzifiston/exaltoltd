<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Production;
use App\Models\Sale;
use Illuminate\Validation\Validator;

trait ChecksFinishedGoodsStock
{
    /**
     * Reject a sale that would dispatch more finished goods than a production batch holds.
     * On edit, the quantities this sale currently holds are added back first (they are
     * released and re-applied when the sale is saved).
     */
    protected function validateFinishedGoodsStock(Validator $validator, ?Sale $sale = null): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $requested = [];
        foreach ((array) $this->input('items', []) as $line) {
            $productionId = $line['production_id'] ?? null;
            if (! $productionId) {
                continue;
            }
            $requested[$productionId] = ($requested[$productionId] ?? 0) + (float) ($line['quantity_sold'] ?? 0);
        }

        if ($requested === []) {
            return;
        }

        $held = [];
        if ($sale) {
            foreach ($sale->items as $item) {
                $held[$item->production_id] = ($held[$item->production_id] ?? 0) + (float) $item->quantity_sold;
            }
        }

        $productions = Production::with('product:id,type')
            ->whereIn('id', array_keys($requested))
            ->get()
            ->keyBy('id');

        foreach ($requested as $productionId => $qty) {
            $production = $productions[$productionId] ?? null;
            if (! $production) {
                continue;
            }

            // Held, rejected or expired stock cannot be sold. A sale already holding units
            // of the batch keeps them, so editing an older sale is not blocked.
            if (! isset($held[$productionId]) && ($reason = $production->unsellableReason())) {
                $validator->errors()->add('items', sprintf(
                    'Batch %s of %s cannot be sold: it is %s.',
                    $production->batch_id,
                    $production->product?->type ?: 'product',
                    $reason,
                ));

                continue;
            }

            $available = (float) $production->quantity_remaining + (float) ($held[$productionId] ?? 0);

            if ($qty > $available + 0.0001) {
                $validator->errors()->add('items', sprintf(
                    'Not enough stock: only %s unit(s) of %s (batch %s) are available, but %s were entered.',
                    $this->trimQty($available),
                    $production->product?->type ?: 'product',
                    $production->batch_id,
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
