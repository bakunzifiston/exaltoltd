<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\InventoryRecord;
use App\Models\Production;
use App\Models\ProductionSample;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\StockReturn;
use App\Models\StockReturnItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Food traceability: one step back and one step forward from any batch or ingredient lot.
 *
 *   backward   batch -> the raw-material lots it drew on -> their suppliers
 *   forward    batch -> every sale line it supplied -> the customers who received it
 *   recall     ingredient lot -> every batch that used it -> every customer of those
 *
 * Everything is read from records the business already keeps: the materials list on each
 * batch and the batch named on each sale line. Nothing here is a copy that can drift.
 */
class TraceabilityService
{
    public function __construct(private readonly ProductionCostingService $costing) {}

    /**
     * The lots a batch was made from, each with its supplier details and the other batches
     * that drew on the same lot (the first question in any ingredient problem).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function backward(Production $batch): Collection
    {
        $materials = collect($this->costing->materials($batch))
            ->map(fn ($m) => ['inventory_id' => (int) ($m['inventory_id'] ?? 0), 'quantity' => (float) ($m['quantity_used'] ?? 0)])
            ->filter(fn ($m) => $m['inventory_id'] > 0);

        $lots = InventoryRecord::withTrashed()->with('supplier')
            ->whereIn('id', $materials->pluck('inventory_id'))->get()->keyBy('id');
        $costs = $this->costing->lotUnitCosts();
        $usage = collect($this->costing->consumption())->groupBy('inventory_id');

        return $materials->map(function (array $m) use ($batch, $lots, $costs, $usage) {
            $unit = $costs[$m['inventory_id']] ?? null;

            return [
                'lot' => $lots[$m['inventory_id']] ?? null,
                'inventory_id' => $m['inventory_id'],
                'quantity' => $m['quantity'],
                'unit_cost' => $unit,
                'cost' => $unit === null ? null : round($m['quantity'] * $unit, 2),
                'other_batches' => collect($usage[$m['inventory_id']] ?? [])
                    ->where('production_id', '!=', $batch->id)
                    ->unique('production_id')
                    ->map(fn ($line) => ['production_id' => $line['production_id'], 'batch_id' => $line['batch_id'], 'date' => $line['production_date'], 'product' => $line['product']])
                    ->values(),
            ];
        })->values();
    }

    /**
     * Every sale line drawn from the batch, newest first, with who received it and whether
     * it was delivered. Legacy sales that name the batch on the sale itself, with no line
     * for it, are included once.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forward(Production $batch): Collection
    {
        $batch->loadMissing(['saleItems.sale.creator', 'saleItems.sale.customer']);

        $row = fn (Sale $sale, float $quantity, float $unitPrice, float $total) => [
            'sale' => $sale,
            'sales_id' => $sale->sales_id,
            'sale_date' => $sale->sale_date ? Carbon::parse($sale->sale_date)->toDateString() : null,
            'customer' => $sale->customer?->name ?: $sale->customer_name,
            'customer_id' => $sale->customer_id,
            'phone' => $sale->customer?->phone ?: $sale->customer_Phone,
            'address' => $sale->delivery_address ?: $sale->customer?->address,
            'delivery_status' => $sale->delivery_status,
            'delivered_at' => $sale->delivered_at,
            'payment_status' => $sale->payment_status,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $total,
            'recorded_by' => $sale->creator?->name,
            'kind' => 'sale',
            'sample' => null,
        ];

        $rows = $batch->saleItems
            ->filter(fn ($item) => $item->sale !== null)
            ->map(fn ($item) => $row($item->sale, (float) $item->quantity_sold, (float) $item->unit_price, (float) $item->line_total));

        $seen = $batch->saleItems->pluck('sale_id')->filter()->all();
        $legacy = Sale::with(['creator', 'customer'])
            ->where('production_id', $batch->id)
            ->when($seen, fn ($q) => $q->whereNotIn('id', $seen))
            ->get()
            ->map(fn (Sale $sale) => $row($sale, (float) $sale->quantity_sold, (float) $sale->selling_price, (float) $sale->total_revenue));

        // Samples reach customers too, so a recall has to find them; they carry no price.
        $samples = ProductionSample::with(['customer', 'creator'])
            ->where('production_id', $batch->id)->where('status', ProductionSample::APPROVED)->get()
            ->map(fn (ProductionSample $sample) => [
                'sale' => null,
                'sales_id' => $sample->reference,
                'sale_date' => $sample->sample_date->toDateString(),
                'customer' => $sample->recipient_name,
                'customer_id' => $sample->customer_id,
                'phone' => $sample->customer?->phone,
                'address' => $sample->customer?->address,
                'delivery_status' => 'Sample',
                'delivered_at' => $sample->approved_at,
                'payment_status' => null,
                'quantity' => (float) $sample->quantity,
                'unit_price' => 0.0,
                'line_total' => 0.0,
                'recorded_by' => $sample->creator?->name,
                'kind' => 'sample',
                'sample' => $sample,
            ]);

        return $rows->concat($legacy)->concat($samples)->sortByDesc('sale_date')->values();
    }

    /**
     * Where the batch's units went, and whether the stock ledger agrees with the sales.
     *
     * @return array<string, mixed>
     */
    public function summary(Production $batch, Collection $forward): array
    {
        $produced = (float) $batch->quantity_produced;
        $remaining = (float) $batch->quantity_remaining;
        $sales = $forward->where('kind', 'sale');
        $soldAll = round((float) $sales->sum('quantity'), 2);
        $samples = round((float) $forward->where('kind', 'sample')->sum('quantity'), 2);
        $returnLines = StockReturnItem::query()->where('production_id', $batch->id)
            ->whereHas('stockReturn', fn ($q) => $q->where('status', StockReturn::APPROVED));
        $restocked = round((float) (clone $returnLines)->sum('quantity_restocked'), 2);
        // Goods back from customers: approved stock returns, plus older sales marked returned.
        $returned = round((float) (clone $returnLines)->sum('quantity_returned') + (float) $sales->where('delivery_status', 'Returned')->sum('quantity'), 2);
        $leftBatch = round($produced - $remaining, 2);
        $expectedLeft = round($soldAll - $restocked + $samples, 2);

        return [
            'produced' => $produced,
            'damaged' => (float) $batch->damaged,
            'sold' => round($soldAll - $returned, 2),
            'delivered' => round((float) $sales->where('delivery_status', RevenueRecognitionService::DELIVERED)->sum('quantity'), 2),
            'returned' => $returned,
            'restocked' => $restocked,
            'samples' => $samples,
            'remaining' => $remaining,
            'customers' => $forward->map(fn ($r) => $r['customer_id'] ?: mb_strtolower(trim((string) $r['customer'])))->filter()->unique()->count(),
            'left_batch' => $leftBatch,
            'sold_all' => $soldAll,
            'expected_left' => $expectedLeft,
            // Units that left the batch (produced - remaining) should equal what was sold,
            // less what came back to the shelf, plus samples; a gap is a data problem.
            'reconciles' => abs($leftBatch - $expectedLeft) < 0.01,
        ];
    }

    /**
     * Everything that happened to the batch and its ingredients, oldest first.
     *
     * @return Collection<int, array{at: Carbon|null, kind: string, event: string, quantity: float|null, detail: string|null, user: string|null}>
     */
    public function timeline(Production $batch): Collection
    {
        $stock = StockMovement::with('user:id,name')->where('production_id', $batch->id)->get()
            ->map(fn (StockMovement $m) => [
                'at' => $m->moved_at,
                'kind' => 'Finished goods',
                'event' => ucfirst(str_replace('_', ' ', (string) $m->type)),
                'quantity' => (float) $m->quantity,
                'detail' => $m->notes,
                'user' => $m->user?->name,
            ]);

        $materials = InventoryMovement::with(['user:id,name', 'inventoryRecord' => fn ($q) => $q->withTrashed()])
            ->where('reference_type', 'production')->where('reference_id', $batch->id)->get()
            ->map(fn (InventoryMovement $m) => [
                'at' => $m->moved_at,
                'kind' => 'Raw material',
                'event' => ucfirst((string) $m->type),
                'quantity' => (float) $m->quantity,
                'detail' => trim(($m->inventoryRecord?->item_name ?? 'Lot #'.$m->inventory_record_id).' · '.$m->notes, ' ·'),
                'user' => $m->user?->name,
            ]);

        $quality = $batch->qualityChecks()->with('checker:id,name')->get()
            ->map(fn ($check) => [
                'at' => $check->checked_at,
                'kind' => 'Quality',
                'event' => $check->typeLabel().': '.ucfirst($check->result),
                'quantity' => null,
                'detail' => $check->notes,
                'user' => $check->checker?->name,
            ]);

        $release = $batch->released_at ? collect([[
            'at' => $batch->released_at,
            'kind' => 'Quality',
            'event' => 'Status set to '.strtolower(Production::QC_STATUSES[$batch->qc_status] ?? $batch->qc_status),
            'quantity' => null,
            'detail' => $batch->release_notes,
            'user' => $batch->releasedBy?->name,
        ]]) : collect();

        return $stock->concat($materials)->concat($quality)->concat($release)
            ->sortBy(fn ($e) => $e['at']?->getTimestamp() ?? 0)->values();
    }

    /**
     * Forward trace from an ingredient lot: every batch that used it and every customer
     * those batches reached. This is the list a recall starts from.
     *
     * @return array{batches: Collection, shipments: Collection, customers: Collection}
     */
    public function recall(InventoryRecord $lot): array
    {
        $used = collect($this->costing->consumption())->where('inventory_id', $lot->id)
            ->groupBy('production_id')->map(fn ($lines) => (float) $lines->sum('quantity'));

        $batches = Production::with('product')->whereIn('id', $used->keys())->orderBy('production_date')->get();

        $shipments = collect();
        $batchRows = $batches->map(function (Production $batch) use ($used, &$shipments) {
            $forward = $this->forward($batch);
            $shipments = $shipments->concat($forward->map(fn ($r) => $r + ['batch' => $batch]));

            return ['batch' => $batch, 'quantity_used' => $used[$batch->id] ?? 0, 'summary' => $this->summary($batch, $forward)];
        });

        $customers = $shipments
            ->groupBy(fn ($r) => $r['customer_id'] ? 'id:'.$r['customer_id'] : 'name:'.mb_strtolower(trim((string) $r['customer'])).'|'.$r['phone'])
            ->map(fn (Collection $rows) => [
                'customer' => $rows->first()['customer'],
                'customer_id' => $rows->first()['customer_id'],
                'phone' => $rows->pluck('phone')->filter()->first(),
                'address' => $rows->pluck('address')->filter()->first(),
                'quantity' => round((float) $rows->sum('quantity'), 2),
                'batches' => $rows->map(fn ($r) => $r['batch']->batch_id)->unique()->values()->all(),
                'last_sale' => $rows->max('sale_date'),
                'delivered' => $rows->contains('delivery_status', RevenueRecognitionService::DELIVERED),
            ])
            ->sortByDesc('quantity')->values();

        return ['batches' => $batchRows, 'shipments' => $shipments->sortByDesc('sale_date')->values(), 'customers' => $customers];
    }
}
