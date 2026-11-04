<?php

namespace App\Services;

use App\Models\InventoryRecord;
use App\Models\Production;
use App\Models\ProductionSample;
use App\Models\StockReturn;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What a unit of finished goods actually cost to make.
 *
 * Shaky manufactures rather than resells: every inventory record is a raw material, and
 * finished goods only exist once a production batch consumes those materials. So the cost
 * of a sale cannot be found by consuming purchase layers of the product sold — no such
 * layers exist. It has to come down the chain the data already records:
 *
 *   raw-material lot cost  ->  batch cost  ->  cost per unit produced  ->  cost of a sale line
 *
 * Because `sale_items.production_id` names the batch each sale came from, this is exact
 * per batch rather than an averaging approximation.
 */
class ProductionCostingService
{
    /**
     * Every batch, loaded once: id, batch number, product label, date, quantities and
     * normalised materials. Statements ask the same questions for twelve or more month
     * ends; answering them from memory replaces a full table scan per month.
     *
     * @var array<int, array{id: int, batch_id: string, product_id: int|null, product: string, date: string, produced: float, damaged: float, materials: array}>|null
     */
    private ?array $batches = null;

    /** @var array<int, float>|null */
    private ?array $lotUnitCosts = null;

    /** @var array<int, array<string, mixed>>|null Every lot, soft-deleted included, read once. */
    private ?array $lotRows = null;

    /** @var list<array{0: int, 1: string, 2: float}>|null [production_id, sale_date, quantity] for delivered lines. */
    private ?array $deliveredSales = null;

    /** @var array<string, float> */
    private array $finishedGoodsCache = [];

    /** Forget everything loaded, after the underlying records change. */
    public function flush(): void
    {
        $this->batches = $this->lotUnitCosts = $this->lotRows = $this->deliveredSales = null;
        $this->finishedGoodsCache = [];
    }

    /**
     * Cost of one unit produced by a batch, or null when it cannot be established.
     *
     * Null rather than zero matters: a batch whose material lots are missing would
     * otherwise look free to make, and every sale from it infinitely profitable. The value
     * is not rounded: rounding it and multiplying back up leaves cents that never clear.
     */
    public function unitCost(Production $production): ?float
    {
        $quantity = (float) $production->quantity_produced;
        $batchCost = $quantity > 0 ? $this->batchCost($production) : null;

        return $batchCost === null ? null : $batchCost / $quantity;
    }

    /** Total raw-material cost consumed by a batch, or null if no material is costable. */
    public function batchCost(Production $production): ?float
    {
        return $this->costOfMaterials($this->materials($production));
    }

    /**
     * Raw materials drawn into production batches dated within a window, one row per
     * material line, valued at the lot's purchase price — the same basis as batchCost().
     *
     * This is what the business means by "cost of goods – raw materials used". Lines whose
     * lot has no usable price keep a null cost so callers can flag them instead of
     * silently treating them as free.
     *
     * @return list<array{production_id: int, batch_id: string, production_date: string, product: string, inventory_id: int, item_name: string, supplier: string, quantity: float, unit_cost: float|null, cost: float|null}>
     */
    public function consumption(?string $from = null, ?string $to = null): array
    {
        $costs = $this->lotUnitCosts();
        $names = $this->lotRows();
        $lines = [];

        foreach ($this->batches() as $batch) {
            if (($from !== null && $batch['date'] < $from) || ($to !== null && $batch['date'] > $to)) {
                continue;
            }
            foreach ($batch['materials'] as $material) {
                $lotId = (int) ($material['inventory_id'] ?? 0);
                $used = (float) ($material['quantity_used'] ?? 0);
                if ($lotId <= 0 || $used <= 0) {
                    continue;
                }
                $unit = $costs[$lotId] ?? null;
                $lines[] = [
                    'production_id' => $batch['id'],
                    'batch_id' => $batch['batch_id'],
                    'production_date' => $batch['date'],
                    'product' => $batch['product'],
                    'inventory_id' => $lotId,
                    'item_name' => $names[$lotId]['item_name'] ?? 'Unknown material #'.$lotId,
                    'supplier' => $names[$lotId]['supplier_name'] ?? '',
                    'quantity' => $used,
                    'unit_cost' => $unit,
                    'cost' => $unit === null ? null : round($used * $unit, 2),
                ];
            }
        }

        return $lines;
    }

    /**
     * Cost of goods sold over a window, on the same basis as the income statement:
     * raw materials consumed + opening finished goods - closing finished goods + raw
     * materials written off + the purchase cost of sale lines whose batch has no material
     * cost (resold goods).
     *
     * @return array{materials_consumed: float, stock_movement: float, written_off: float, resold: float, total: float}
     */
    public function costOfGoodsSold(?string $from, string $to): array
    {
        $consumed = round(array_sum(array_filter(array_column($this->consumption($from, $to), 'cost'), fn ($c) => $c !== null)), 2);
        $opening = $from ? $this->finishedGoodsValueAt(Carbon::parse($from)->subDay()->toDateString()) : 0.0;
        $movement = round($opening - $this->finishedGoodsValueAt($to), 2);
        $writtenOff = round(array_sum(array_column(array_filter(
            app(InventoryValuationService::class)->writeOffs(),
            fn (array $w) => ($from === null || $w['date'] >= $from) && $w['date'] <= $to,
        ), 'value')), 2);
        $resold = round(array_sum(array_column(array_filter(
            $this->resoldLines(),
            fn (array $l) => ($from === null || $l['date'] >= $from) && $l['date'] <= $to,
        ), 'cost')), 2);

        return [
            'materials_consumed' => $consumed,
            'stock_movement' => $movement,
            'written_off' => $writtenOff,
            'resold' => $resold,
            'total' => round($consumed + $movement + $writtenOff + $resold, 2),
        ];
    }

    /**
     * Delivered sale lines whose batch carries no material cost, at the purchase cost
     * SaleCostingService froze on them. These are goods bought in and resold as they are;
     * they leave raw stock, not finished goods.
     *
     * @return list<array{date: string, product_id: int|null, cost: float}>
     */
    public function resoldLines(): array
    {
        $units = $this->unitCostsFor([]);

        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.delivery_status', RevenueRecognitionService::DELIVERED)
            ->whereNotNull('sale_items.line_cogs')
            ->get(['sale_items.production_id', 'sale_items.product_id', 'sale_items.line_cogs', 'sales.sale_date'])
            ->filter(fn ($line) => ($units[$line->production_id] ?? null) === null)
            ->map(fn ($line) => ['date' => substr((string) $line->sale_date, 0, 10), 'product_id' => $line->product_id, 'cost' => (float) $line->line_cogs])
            ->values()->all();
    }

    /**
     * Normalise the materials list.
     *
     * `inventory_record_id` is the list stock is actually drawn from, so it is read first;
     * the legacy `raw_materials_used` is only a fallback for batches that predate it. Both
     * are sometimes a JSON array and sometimes a JSON string holding one.
     */
    public function materials(Production $production): array
    {
        return $this->normaliseMaterials($production->inventory_record_id, $production->raw_materials_used);
    }

    /**
     * Unit cost of every raw-material lot, keyed by inventory record id, on the rule in
     * InventoryRecord::costPerUnit().
     *
     * @return array<int, float>
     */
    public function lotUnitCosts(): array
    {
        if ($this->lotUnitCosts !== null) {
            return $this->lotUnitCosts;
        }

        $costs = [];
        foreach ($this->lots() as $id => $lot) {
            if ($lot['unit'] !== null) {
                $costs[$id] = $lot['unit'];
            }
        }

        return $this->lotUnitCosts = $costs;
    }

    /**
     * Value of finished goods still on hand at a date.
     *
     * Production turns raw materials into finished goods: one asset becomes another. If the
     * balance sheet counted only raw materials, every batch would look like value being
     * destroyed. Stock is units produced less units damaged and sold from that batch up to
     * the date, valued at the batch's share of its material cost, the same basis as COGS.
     */
    public function finishedGoodsValueAt(string $asOfDate): float
    {
        return $this->finishedGoodsValuesAt([$asOfDate])[substr($asOfDate, 0, 10)];
    }

    /**
     * finishedGoodsValueAt() for many dates from one read of the batches and sales.
     *
     * @param  array<int, string>  $dates
     * @return array<string, float> keyed by Y-m-d
     */
    public function finishedGoodsValuesAt(array $dates): array
    {
        $out = [];
        foreach ($dates as $date) {
            $date = substr((string) $date, 0, 10);
            if (! array_key_exists($date, $this->finishedGoodsCache)) {
                $sold = [];
                foreach ($this->deliveredSales() as [$productionId, $saleDate, $quantity]) {
                    if ($saleDate <= $date) {
                        $sold[$productionId] = ($sold[$productionId] ?? 0) + $quantity;
                    }
                }

                $total = 0.0;
                foreach ($this->batches() as $batch) {
                    if ($batch['date'] > $date || $batch['produced'] <= 0) {
                        continue;
                    }
                    $cost = $this->costOfMaterials($batch['materials']);
                    $onHand = $batch['produced'] - $batch['damaged'] - ($sold[$batch['id']] ?? 0);
                    if ($cost !== null && $onHand > 0) {
                        $total += $cost * $onHand / $batch['produced'];
                    }
                }
                $this->finishedGoodsCache[$date] = round($total, 2);
            }
            $out[$date] = $this->finishedGoodsCache[$date];
        }

        return $out;
    }

    /**
     * Unit costs keyed by production id. With no ids, every batch.
     *
     * @return array<int, float|null>
     */
    public function unitCostsFor(array $productionIds): array
    {
        $wanted = array_flip(array_filter($productionIds));
        $out = [];
        foreach ($this->batches() as $id => $batch) {
            if ($wanted !== [] && ! isset($wanted[$id])) {
                continue;
            }
            $cost = $batch['produced'] > 0 ? $this->costOfMaterials($batch['materials']) : null;
            $out[$id] = $cost === null ? null : $cost / $batch['produced'];
        }

        return $out;
    }

    // ── Internals ────────────────────────────────────────────────────────────

    /** Cost of a materials list at lot prices, or null when not one line can be priced. */
    private function costOfMaterials(array $materials): ?float
    {
        $costs = $this->lotUnitCosts();
        $total = 0.0;
        $costed = 0;

        foreach ($materials as $material) {
            $lotId = (int) ($material['inventory_id'] ?? 0);
            $used = (float) ($material['quantity_used'] ?? 0);
            if ($lotId <= 0 || $used <= 0 || ! isset($costs[$lotId])) {
                continue;
            }
            $total += $used * $costs[$lotId];
            $costed++;
        }

        // A batch where not one material could be priced is not costable at all.
        return $costed === 0 ? null : round($total, 2);
    }

    private function normaliseMaterials(mixed ...$sources): array
    {
        foreach ($sources as $value) {
            if (is_string($value)) {
                $value = json_decode($value, true);
            }
            if (is_array($value) && $value !== []) {
                return array_values(array_filter($value, 'is_array'));
            }
        }

        return [];
    }

    private function batches(): array
    {
        if ($this->batches !== null) {
            return $this->batches;
        }

        $batches = [];
        foreach (Production::query()
            ->leftJoin('products', 'products.id', '=', 'productions.product_id')
            ->orderBy('productions.production_date')->orderBy('productions.id')
            ->toBase()
            ->get([
                'productions.id', 'productions.batch_id', 'productions.product_id', 'productions.production_date',
                'productions.quantity_produced', 'productions.damaged', 'productions.raw_materials_used',
                'productions.inventory_record_id', 'products.type as product_type', 'products.name as product_name',
            ]) as $row) {
            $batches[(int) $row->id] = [
                'id' => (int) $row->id,
                'batch_id' => (string) $row->batch_id,
                'product_id' => $row->product_id !== null ? (int) $row->product_id : null,
                'product' => (string) ($row->product_type ?: $row->product_name ?: 'Unspecified product'),
                'date' => substr((string) $row->production_date, 0, 10),
                'produced' => (float) $row->quantity_produced,
                'damaged' => (float) $row->damaged,
                'materials' => $this->normaliseMaterials($row->inventory_record_id, $row->raw_materials_used),
            ];
        }

        return $this->batches = $batches;
    }

    /**
     * Every live raw-material lot, keyed by id: the one read of inventory records that the
     * costing and the stock valuation both work from.
     *
     * @return array<int, array{id: int, date: string, item_name: string, supplier_name: string, quantity_in: float, quantity_out: float, damaged: float, unit: float|null}>
     */
    public function lots(): array
    {
        return array_filter($this->lotRows(), fn (array $lot) => ! $lot['deleted']);
    }

    private function lotRows(): array
    {
        // Soft-deleted lots are kept here only so a batch that drew from one stays named.
        return $this->lotRows ??= InventoryRecord::withTrashed()->toBase()
            ->get(['id', 'record_date', 'item_name', 'supplier_name', 'quantity_in', 'quantity_out', 'damaged', 'unit_cost', 'total_amount', 'deleted_at'])
            ->mapWithKeys(fn ($lot) => [(int) $lot->id => [
                'id' => (int) $lot->id,
                'date' => substr((string) $lot->record_date, 0, 10),
                'item_name' => trim((string) $lot->item_name),
                'supplier_name' => (string) $lot->supplier_name,
                'quantity_in' => (float) $lot->quantity_in,
                'quantity_out' => (float) $lot->quantity_out,
                'damaged' => (float) $lot->damaged,
                'unit' => InventoryRecord::costPerUnit($lot->quantity_in, $lot->unit_cost, $lot->total_amount),
                'deleted' => $lot->deleted_at !== null,
            ]])->all();
    }

    /**
     * Every dated movement of finished goods out of a batch other than damage:
     * delivered sales out, approved samples out, and returned units put back on the shelf
     * after inspection (a negative outflow, on the return date).
     *
     * @return list<array{0: int, 1: string, 2: float}> [production_id, date, quantity out]
     */
    private function deliveredSales(): array
    {
        if ($this->deliveredSales !== null) {
            return $this->deliveredSales;
        }

        $sold = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.delivery_status', RevenueRecognitionService::DELIVERED)
            ->whereNotNull('sale_items.production_id')
            ->get(['sale_items.production_id', 'sales.sale_date', 'sale_items.quantity_sold'])
            ->map(fn ($row) => [(int) $row->production_id, substr((string) $row->sale_date, 0, 10), (float) $row->quantity_sold]);

        $restocked = DB::table('stock_return_items')
            ->join('stock_returns', 'stock_returns.id', '=', 'stock_return_items.stock_return_id')
            ->where('stock_returns.status', StockReturn::APPROVED)
            ->whereNotNull('stock_return_items.production_id')
            ->where('stock_return_items.quantity_restocked', '>', 0)
            ->get(['stock_return_items.production_id', 'stock_returns.return_date', 'stock_return_items.quantity_restocked'])
            ->map(fn ($row) => [(int) $row->production_id, substr((string) $row->return_date, 0, 10), -1 * (float) $row->quantity_restocked]);

        $samples = DB::table('production_samples')
            ->where('status', ProductionSample::APPROVED)
            ->get(['production_id', 'sample_date', 'quantity'])
            ->map(fn ($row) => [(int) $row->production_id, substr((string) $row->sample_date, 0, 10), (float) $row->quantity]);

        return $this->deliveredSales = $sold->concat($restocked)->concat($samples)->all();
    }

    /**
     * Approved samples at their frozen cost, for the income statement.
     *
     * @return list<array{date: string, cost: float}>
     */
    public function sampleCosts(): array
    {
        return DB::table('production_samples')
            ->where('status', ProductionSample::APPROVED)
            ->whereNotNull('cost_value')
            ->get(['sample_date', 'cost_value'])
            ->map(fn ($row) => ['date' => substr((string) $row->sample_date, 0, 10), 'cost' => (float) $row->cost_value])
            ->all();
    }
}
