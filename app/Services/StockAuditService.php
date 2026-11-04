<?php

namespace App\Services;

use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\Production;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;

/**
 * Surfaces inventory/stock records that are internally impossible — the data that
 * predates the validation guards (or was entered before them) and undermines trust.
 */
class StockAuditService
{
    /**
     * @return array<string, array{label: string, hint: string, rows: \Illuminate\Support\Collection}>
     */
    public function run(): array
    {
        return [
            'oversold_batches' => [
                'label' => 'Batches sold beyond what was produced',
                'hint' => 'Units sold from this batch exceed the quantity ever produced in it.',
                'rows' => $this->oversoldBatches(),
            ],
            'oversold_products' => [
                'label' => 'Products sold beyond total production',
                'hint' => 'Across all batches, more units were sold than were ever produced for this product.',
                'rows' => $this->oversoldProducts(),
            ],
            'negative_raw_lots' => [
                'label' => 'Raw-material lots used beyond intake',
                'hint' => 'Quantity out exceeds quantity in — implies consuming stock that never arrived.',
                'rows' => $this->negativeRawLots(),
            ],
            'negative_finished_goods' => [
                'label' => 'Batches with negative remaining stock',
                'hint' => 'Sellable remainder dropped below zero.',
                'rows' => $this->negativeFinishedGoods(),
            ],
        ];
    }

    public function totalIssues(): int
    {
        return collect($this->run())->sum(fn ($section) => $section['rows']->count());
    }

    private function oversoldBatches()
    {
        // Units returned to the shelf can be sold again, so they are netted off first.
        $sold = SaleItem::query()
            ->select('production_id', DB::raw('SUM(quantity_sold) as sold'))
            ->groupBy('production_id')
            ->pluck('sold', 'production_id');
        foreach (\App\Models\StockReturnItem::query()
            ->whereHas('stockReturn', fn ($q) => $q->where('status', \App\Models\StockReturn::APPROVED))
            ->groupBy('production_id')->selectRaw('production_id, SUM(quantity_restocked) as restocked')
            ->pluck('restocked', 'production_id') as $productionId => $restocked) {
            $sold[$productionId] = (float) ($sold[$productionId] ?? 0) - (float) $restocked;
        }

        return Production::query()
            ->with('product:id,type')
            ->get()
            ->filter(fn (Production $p) => (float) ($sold[$p->id] ?? 0) > (float) $p->quantity_produced + 0.0001)
            ->map(fn (Production $p) => [
                'id' => $p->id,
                'label' => $p->batch_id . ' · ' . ($p->product?->type ?? '—'),
                'produced' => (float) $p->quantity_produced,
                'sold' => (float) ($sold[$p->id] ?? 0),
                'over_by' => round((float) ($sold[$p->id] ?? 0) - (float) $p->quantity_produced, 2),
                'url' => route('admin.productions.show', $p->id),
            ])
            ->values();
    }

    private function oversoldProducts()
    {
        $produced = Production::query()
            ->select('product_id', DB::raw('SUM(quantity_produced) as produced'))
            ->groupBy('product_id')
            ->pluck('produced', 'product_id');

        $sold = SaleItem::query()
            ->select('product_id', DB::raw('SUM(quantity_sold) as sold'))
            ->groupBy('product_id')
            ->pluck('sold', 'product_id');

        return Product::query()
            ->get(['id', 'type'])
            ->filter(fn (Product $p) => (float) ($sold[$p->id] ?? 0) > (float) ($produced[$p->id] ?? 0) + 0.0001)
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'label' => $p->type,
                'produced' => (float) ($produced[$p->id] ?? 0),
                'sold' => (float) ($sold[$p->id] ?? 0),
                'over_by' => round((float) ($sold[$p->id] ?? 0) - (float) ($produced[$p->id] ?? 0), 2),
                'url' => route('admin.products.show', $p->id),
            ])
            ->values();
    }

    private function negativeRawLots()
    {
        return InventoryRecord::query()
            ->whereColumn('quantity_out', '>', 'quantity_in')
            ->get()
            ->map(fn (InventoryRecord $r) => [
                'id' => $r->id,
                'label' => $r->item_name . ($r->lot_number ? ' · ' . $r->lot_number : ''),
                'in' => (float) $r->quantity_in,
                'out' => (float) $r->quantity_out,
                'over_by' => round((float) $r->quantity_out - (float) $r->quantity_in, 2),
                'url' => route('admin.inventory-records.show', $r->id),
            ])
            ->values();
    }

    private function negativeFinishedGoods()
    {
        return Production::query()
            ->where('quantity_remaining', '<', 0)
            ->with('product:id,type')
            ->get()
            ->map(fn (Production $p) => [
                'id' => $p->id,
                'label' => $p->batch_id . ' · ' . ($p->product?->type ?? '—'),
                'produced' => (float) $p->quantity_produced,
                'remaining' => (float) $p->quantity_remaining,
                'over_by' => round(abs((float) $p->quantity_remaining), 2),
                'url' => route('admin.productions.show', $p->id),
            ])
            ->values();
    }
}
