<?php

namespace App\Services;

use App\Models\Production;
use Illuminate\Support\Collection;

class StorefrontCheckoutService
{
    /**
     * Build SaleWorkflowService item payloads by allocating storefront cart lines
     * across production batches (FIFO by production id). Matches ERP sale posting
     * so SaleItem hooks deduct finished-goods stock correctly.
     *
     * @param  Collection<int, array{product: object, quantity: int, unit_price: float, line_total: float}>  $rows
     * @return array<int, array{product_id: int, production_id: int, quantity_sold: float, unit_price: float}>
     */
    public function allocateItems(Collection $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $productId = (int) $row['product']->id;
            $qtyNeeded = (float) $row['quantity'];
            $unitPrice = (float) $row['unit_price'];

            $sellable = (float) ($row['product']->sellable_qty ?? 0);
            if ($qtyNeeded > $sellable + 0.0001) {
                $name = $row['product']->name ?? 'this product';
                throw new \InvalidArgumentException(
                    "We can't fulfill the requested quantity of \"{$name}\" right now. Please submit an inquiry through our contact page and our team will get back to you within 24 hours."
                );
            }

            foreach ($this->allocateProduction($productId, $qtyNeeded) as $chunk) {
                $items[] = [
                    'product_id' => $productId,
                    'production_id' => $chunk['production_id'],
                    'quantity_sold' => $chunk['quantity'],
                    'unit_price' => $unitPrice,
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<int, array{production_id: int, quantity: float}>
     */
    private function allocateProduction(int $productId, float $qtyNeeded): array
    {
        $remaining = $qtyNeeded;
        $chunks = [];

        // Only released, in-date batches, earliest best-before first (first expired, first out).
        $batches = Production::query()
            ->sellable()
            ->where('product_id', $productId)
            ->where('quantity_remaining', '>', 0)
            ->fefo()
            ->get(['id', 'quantity_remaining']);

        foreach ($batches as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }

            $available = (float) $batch->quantity_remaining;
            $take = min($remaining, $available);

            if ($take <= 0) {
                continue;
            }

            $chunks[] = [
                'production_id' => (int) $batch->id,
                'quantity' => $take,
            ];

            $remaining -= $take;
        }

        if ($remaining > 0.0001) {
            throw new \InvalidArgumentException(
                'The requested quantity is not available right now. Please submit an inquiry through our contact page and our team will confirm availability within 24 hours.'
            );
        }

        return $chunks;
    }
}
