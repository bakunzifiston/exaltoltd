<?php

namespace App\Services;

use App\Models\Production;
use App\Models\SaleItem;
use App\Support\DateBounds;
use Illuminate\Support\Facades\DB;

/**
 * Gross margin per SKU and per production batch.
 *
 * The 2025 workbook lists revenue by product but pools all cost into one COGS block, so it
 * cannot say which of the Neza products actually earns money. Costing each sale line makes
 * that answerable.
 */
class ProductProfitabilityService
{
    public function __construct(private readonly RevenueRecognitionService $revenue) {}

    /**
     * Per-product revenue, cost and margin for delivered sales in a window.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byProduct(string $from, string $to): array
    {
        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.delivery_status', RevenueRecognitionService::DELIVERED)
            ->whereBetween('sales.sale_date', DateBounds::between($from, $to))
            ->groupBy('products.id', 'products.type', 'products.name')
            ->select([
                'products.id as product_id',
                DB::raw('COALESCE(products.type, products.name) as product_name'),
                DB::raw('SUM(sale_items.quantity_sold) as quantity'),
                DB::raw('SUM(sale_items.line_total) as revenue'),
                DB::raw('SUM(sale_items.line_cogs) as cost'),
                DB::raw('COUNT(sale_items.id) as line_count'),
                DB::raw('SUM(CASE WHEN sale_items.line_cogs IS NULL THEN 1 ELSE 0 END) as uncosted_lines'),
            ])
            ->orderByDesc('revenue')
            ->get();

        return $this->shape($rows, 'product_name');
    }

    /**
     * Per-batch revenue, cost and margin. Answers which production runs were profitable,
     * which the workbooks cannot do at all.
     *
     * @return array<int, array<string, mixed>>
     */
    public function byBatch(string $from, string $to): array
    {
        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('productions', 'productions.id', '=', 'sale_items.production_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.delivery_status', RevenueRecognitionService::DELIVERED)
            ->whereBetween('sales.sale_date', DateBounds::between($from, $to))
            ->groupBy('productions.id', 'productions.batch_id', 'productions.production_date', 'products.type', 'products.name')
            ->select([
                'productions.id as production_id',
                DB::raw("COALESCE(productions.batch_id, 'Unbatched') as product_name"),
                'productions.production_date',
                DB::raw('SUM(sale_items.quantity_sold) as quantity'),
                DB::raw('SUM(sale_items.line_total) as revenue'),
                DB::raw('SUM(sale_items.line_cogs) as cost'),
                DB::raw('COUNT(sale_items.id) as line_count'),
                DB::raw('SUM(CASE WHEN sale_items.line_cogs IS NULL THEN 1 ELSE 0 END) as uncosted_lines'),
            ])
            ->orderByDesc('revenue')
            ->get();

        return $this->shape($rows, 'product_name');
    }

    /** Headline totals for the same window. */
    public function summary(string $from, string $to): array
    {
        $revenue = $this->revenue->recognisedRevenue($from, $to);

        $cost = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.delivery_status', RevenueRecognitionService::DELIVERED)
            ->whereBetween('sales.sale_date', DateBounds::between($from, $to))
            ->sum('sale_items.line_cogs');

        $uncosted = (int) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.delivery_status', RevenueRecognitionService::DELIVERED)
            ->whereBetween('sales.sale_date', DateBounds::between($from, $to))
            ->whereNull('sale_items.line_cogs')
            ->count();

        return [
            'revenue' => round($revenue, 2),
            'cost' => round($cost, 2),
            'margin' => round($revenue - $cost, 2),
            'margin_pct' => $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 1) : null,
            'uncosted_lines' => $uncosted,
        ];
    }

    /**
     * Batches whose implied unit cost is too low to be credible against the price the goods
     * actually sold for.
     *
     * This is a heuristic, not a finding: it flags where to look, it does not prove the cost
     * is wrong. In practice it catches legacy or opening-stock batches that were created to
     * hold finished goods without recording the materials that went into them, which makes
     * the goods look nearly free to produce and the margin near 100%.
     *
     * @return array<int, array<string, mixed>>
     */
    public function costQualityWarnings(string $from, string $to, float $threshold = 0.05): array
    {
        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->leftJoin('productions', 'productions.id', '=', 'sale_items.production_id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.delivery_status', RevenueRecognitionService::DELIVERED)
            ->whereBetween('sales.sale_date', DateBounds::between($from, $to))
            ->whereNotNull('sale_items.line_cogs')
            ->whereNotNull('sale_items.production_id')
            ->groupBy('productions.id', 'productions.batch_id', 'products.type', 'products.name')
            ->select([
                'productions.batch_id',
                DB::raw('COALESCE(products.type, products.name) as product_name'),
                DB::raw('SUM(sale_items.quantity_sold) as quantity'),
                DB::raw('SUM(sale_items.line_total) as revenue'),
                DB::raw('SUM(sale_items.line_cogs) as cost'),
            ])
            ->get();

        $warnings = [];
        foreach ($rows as $row) {
            $quantity = (float) $row->quantity;
            $revenue = (float) $row->revenue;
            if ($quantity <= 0 || $revenue <= 0) {
                continue;
            }

            $unitCost = (float) $row->cost / $quantity;
            $unitPrice = $revenue / $quantity;

            if ($unitPrice > 0 && $unitCost / $unitPrice < $threshold) {
                $warnings[] = [
                    'batch_id' => $row->batch_id ?: 'Unbatched',
                    'product' => $row->product_name,
                    'unit_cost' => round($unitCost, 2),
                    'unit_price' => round($unitPrice, 2),
                    'cost_share' => round(($unitCost / $unitPrice) * 100, 1),
                ];
            }
        }

        usort($warnings, fn ($a, $b) => $a['cost_share'] <=> $b['cost_share']);

        return $warnings;
    }

    /**
     * A row whose lines are not all costed reports a null margin rather than a flattering
     * one: an uncosted line contributes revenue with no cost against it.
     */
    private function shape($rows, string $labelKey): array
    {
        return $rows->map(function ($row) use ($labelKey) {
            $revenue = (float) $row->revenue;
            $cost = $row->cost === null ? null : (float) $row->cost;
            $complete = (int) $row->uncosted_lines === 0;

            return [
                'label' => $row->{$labelKey} ?: 'Unspecified product',
                'production_date' => $row->production_date ?? null,
                'quantity' => (float) $row->quantity,
                'revenue' => round($revenue, 2),
                'cost' => $complete && $cost !== null ? round($cost, 2) : null,
                'margin' => $complete && $cost !== null ? round($revenue - $cost, 2) : null,
                'margin_pct' => $complete && $cost !== null && $revenue > 0
                    ? round((($revenue - $cost) / $revenue) * 100, 1)
                    : null,
                'uncosted_lines' => (int) $row->uncosted_lines,
            ];
        })->all();
    }
}
