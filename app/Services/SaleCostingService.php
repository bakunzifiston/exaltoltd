<?php

namespace App\Services;

use App\Models\SaleItem;
use App\Support\DateBounds;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Freezes the cost of each sale line onto the line itself.
 *
 * Two costing paths, in priority order:
 *
 *  1. **Manufactured goods.** The line names the production batch it came from, so its
 *     cost is the batch's cost per unit: raw materials consumed divided by units produced.
 *     This is how nearly everything Shaky sells is costed, and it is exact per batch.
 *  2. **Resold goods.** Where there is no batch, fall back to FIFO over purchase layers of
 *     the product itself, which is the usual buy-and-resell case.
 *
 * A line that neither path can price is left null rather than zero. Zero would let it
 * contribute revenue with no cost against it and quietly overstate margin.
 */
class SaleCostingService
{
    public function __construct(
        private readonly InventoryValuationService $valuation,
        private readonly ProductionCostingService $production,
    ) {}

    /**
     * @return array{lines: int, costed_from_batch: int, costed_from_purchases: int, uncosted: int, total_cost: float}
     */
    public function recost(?string $from = null, ?string $to = null): array
    {
        $from ??= (string) SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')->min('sales.sale_date');
        $to ??= (string) SaleItem::query()->join('sales', 'sales.id', '=', 'sale_items.sale_id')->max('sales.sale_date');

        if ($from === '' || $to === '') {
            return ['lines' => 0, 'costed_from_batch' => 0, 'costed_from_purchases' => 0, 'uncosted' => 0, 'total_cost' => 0.0];
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        $purchaseCosts = $this->valuation->costPerSaleItem($start->toDateString(), $end->toDateString());

        $batchUnitCosts = $this->production->unitCostsFor(
            SaleItem::query()
                ->whereHas('sale', fn ($q) => $q->whereBetween('sale_date', DateBounds::between($start, $end)))
                ->whereNotNull('production_id')
                ->distinct()->pluck('production_id')->all()
        );

        $changed = false;
        $lines = 0;
        $fromBatch = 0;
        $fromPurchases = 0;
        $uncosted = 0;
        $total = 0.0;

        SaleItem::query()
            ->whereHas('sale', fn ($q) => $q->whereBetween('sale_date', DateBounds::between($start, $end)))
            ->chunkById(500, function ($items) use ($purchaseCosts, $batchUnitCosts, &$lines, &$fromBatch, &$fromPurchases, &$uncosted, &$total, &$changed): void {
                foreach ($items as $item) {
                    $lines++;
                    $quantity = (float) $item->quantity_sold;

                    $unit = null;
                    $cost = null;

                    $batchUnit = $batchUnitCosts[$item->production_id] ?? null;
                    if ($batchUnit !== null && $quantity > 0) {
                        // The line's share of the batch cost; unit_cost is for display only.
                        $unit = round($batchUnit, 2);
                        $cost = round($batchUnit * $quantity, 2);
                        $fromBatch++;
                    } elseif (isset($purchaseCosts[$item->id])) {
                        $cost = round($purchaseCosts[$item->id], 2);
                        $unit = $quantity > 0 ? round($cost / $quantity, 2) : null;
                        $fromPurchases++;
                    } else {
                        $uncosted++;
                    }

                    if ($cost !== null) {
                        $total = round($total + $cost, 2);
                    }

                    // Most lines are already costed; rewriting them would be one UPDATE each.
                    if ($this->differs($item->unit_cost, $unit) || $this->differs($item->line_cogs, $cost)) {
                        DB::table('sale_items')->where('id', $item->id)
                            ->update(['unit_cost' => $unit, 'line_cogs' => $cost]);
                        $changed = true;
                    }
                }
            });

        // These writes bypass model events, so the valuation caches are refreshed here.
        if ($changed) {
            $this->production->flush();
            $this->valuation->flush();
        }

        return [
            'lines' => $lines,
            'costed_from_batch' => $fromBatch,
            'costed_from_purchases' => $fromPurchases,
            'uncosted' => $uncosted,
            'total_cost' => $total,
        ];
    }

    private function differs(mixed $stored, ?float $value): bool
    {
        return $stored === null ? $value !== null : ($value === null || abs((float) $stored - $value) >= 0.005);
    }
}
