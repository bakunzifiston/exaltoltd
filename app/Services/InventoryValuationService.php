<?php

namespace App\Services;

use App\Models\InventoryRecord;
use App\Models\SaleItem;
use App\Support\DateBounds;
use Carbon\Carbon;

class InventoryValuationService
{
    public function __construct(private readonly ProductionCostingService $production) {}

    /** @var list<array{0: string, 1: float}>|null Dated value changes to raw stock: [date, signed value]. */
    private ?array $rawEvents = null;

    /** @var list<array{date: string, inventory_id: int, item_name: string, quantity: float, value: float}>|null */
    private ?array $writeOffs = null;

    /** Forget everything loaded, after the underlying records change. */
    public function flush(): void
    {
        $this->layerCache = [];
        $this->rawEvents = $this->writeOffs = null;
    }

    /**
     * Raw-material stock value at each date, from one read of lots, batches and sales.
     *
     * Each lot is valued on its own price (InventoryRecord::costPerUnit) and relieved when
     * a batch dated on or before the date drew on it — the same quantities and prices the
     * income statement charges as raw materials consumed, so what leaves raw stock is
     * exactly what enters finished goods. Damaged quantities and issues not explained by
     * any batch are relieved on the lot's own date and charged as written off. Goods bought
     * in and resold leave at the cost frozen on their sale line.
     *
     * @param  array<int, string>  $dates
     * @return array<string, float> keyed by Y-m-d
     */
    public function rawStockValuesAt(array $dates): array
    {
        $events = $this->rawEvents();
        $out = [];
        foreach ($dates as $date) {
            $date = substr((string) $date, 0, 10);
            $total = 0.0;
            foreach ($events as [$eventDate, $value]) {
                if ($eventDate <= $date) {
                    $total += $value;
                }
            }
            $out[$date] = round($total, 2);
        }

        return $out;
    }

    /**
     * Raw materials that left stock without going into a batch: damaged quantities, and
     * issues recorded on a lot beyond what batches account for (for example historical
     * usage keyed in before production was recorded). Dated on the lot's own date, since
     * that is the only date recorded for them.
     *
     * @return list<array{date: string, inventory_id: int, item_name: string, quantity: float, value: float}>
     */
    public function writeOffs(): array
    {
        if ($this->writeOffs !== null) {
            return $this->writeOffs;
        }

        $usedByBatches = [];
        foreach ($this->production->consumption() as $line) {
            $usedByBatches[$line['inventory_id']] = ($usedByBatches[$line['inventory_id']] ?? 0) + $line['quantity'];
        }

        $writeOffs = [];
        foreach ($this->production->lots() as $lot) {
            if ($lot['unit'] === null) {
                continue;
            }
            $unexplained = $lot['quantity_out'] - ($usedByBatches[$lot['id']] ?? 0);
            // Issues are stored to two decimals while batch usage may carry more.
            $quantity = $lot['damaged'] + ($unexplained > 0.01 ? $unexplained : 0);
            if ($quantity > 0) {
                $writeOffs[] = ['date' => $lot['date'], 'inventory_id' => $lot['id'], 'item_name' => $lot['item_name'], 'quantity' => $quantity, 'value' => round($quantity * $lot['unit'], 2)];
            }
        }

        return $this->writeOffs = $writeOffs;
    }

    private function rawEvents(): array
    {
        if ($this->rawEvents !== null) {
            return $this->rawEvents;
        }

        $events = [];
        foreach ($this->production->lots() as $lot) {
            if ($lot['unit'] !== null) {
                $events[] = [$lot['date'], $lot['quantity_in'] * $lot['unit']];
            }
        }
        foreach ($this->production->consumption() as $line) {
            if ($line['cost'] !== null) {
                $events[] = [$line['production_date'], -1 * $line['cost']];
            }
        }
        foreach ($this->writeOffs() as $writeOff) {
            $events[] = [$writeOff['date'], -1 * $writeOff['value']];
        }
        foreach ($this->production->resoldLines() as $line) {
            $events[] = [$line['date'], -1 * $line['cost']];
        }

        return $this->rawEvents = $events;
    }

    /**
     * FIFO: Build remaining layers per item (product_id or item_name).
     * Each layer is [quantity_remaining, unit_cost].
     */
    /** Per-request memo for getFifoLayers(), which otherwise rescans every inventory record. */
    protected array $layerCache = [];

    public function getFifoLayers(?string $asOfDate = null): array
    {
        if (array_key_exists((string) $asOfDate, $this->layerCache)) {
            return $this->layerCache[(string) $asOfDate];
        }

        $query = InventoryRecord::query()
            ->orderBy('record_date')
            ->orderBy('id');

        if ($asOfDate) {
            $query->where('record_date', '<=', $asOfDate);
        }

        $records = $query->get();
        $layersByKey = [];

        foreach ($records as $record) {
            $key = $this->itemKey($record);
            $unitCost = max(0, (float) ($record->unit_cost ?? 0));

            if (($record->quantity_in ?? 0) > 0) {
                if (! isset($layersByKey[$key])) {
                    $layersByKey[$key] = [];
                }
                $layersByKey[$key][] = [(float) $record->quantity_in, $unitCost];
            }

            $toConsume = (float) ($record->quantity_out ?? 0);
            if ($toConsume > 0 && isset($layersByKey[$key])) {
                $layersByKey[$key] = $this->consumeFromLayers($layersByKey[$key], $toConsume);
            }
        }

        return $this->layerCache[(string) $asOfDate] = $layersByKey;
    }

    /**
     * Total stock value using FIFO (all items).
     */
    public function getFifoTotalValue(?string $asOfDate = null): float
    {
        $layers = $this->getFifoLayers($asOfDate);
        $total = 0;
        foreach ($layers as $itemLayers) {
            foreach ($itemLayers as [$qty, $cost]) {
                $total += $qty * $cost;
            }
        }

        return round($total, 2);
    }

    /**
     * FIFO cost attributable to each individual sale line in a window.
     *
     * Returns [saleItemId => cost]. A line whose product has no matching purchase layer is
     * absent from the result rather than present with a zero, so callers can tell
     * "cost nothing" apart from "cannot be costed".
     */
    public function costPerSaleItem(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $layers = $this->getFifoLayers($start->copy()->subDay()->format('Y-m-d'));

        $events = [];
        foreach (InventoryRecord::query()
            ->whereBetween('record_date', DateBounds::between($start, $end))
            ->orderBy('record_date')->orderBy('id')->get() as $record) {
            $events[] = ['date' => Carbon::parse($record->record_date), 'seq' => $record->id, 'type' => 'inventory', 'record' => $record];
        }
        foreach (SaleItem::query()
            ->with(['sale', 'product'])
            ->whereHas('sale', fn ($q) => $q->whereBetween('sale_date', DateBounds::between($start, $end))
                ->where('delivery_status', RevenueRecognitionService::DELIVERED))
            ->get() as $item) {
            $events[] = ['date' => Carbon::parse($item->sale->sale_date), 'seq' => $item->id, 'type' => 'sale_item', 'sale_item' => $item];
        }

        usort($events, fn ($a, $b) => [$a['date']->getTimestamp(), $a['type'] === 'sale_item' ? 1 : 0, $a['seq']]
            <=> [$b['date']->getTimestamp(), $b['type'] === 'sale_item' ? 1 : 0, $b['seq']]);

        $costs = [];
        foreach ($events as $event) {
            if ($event['type'] === 'inventory') {
                $record = $event['record'];
                $key = $this->itemKey($record);
                if (($record->quantity_in ?? 0) > 0) {
                    $layers[$key][] = [(float) $record->quantity_in, max(0, (float) ($record->unit_cost ?? 0))];
                }
                $toConsume = (float) ($record->quantity_out ?? 0);
                if ($toConsume > 0 && isset($layers[$key])) {
                    $layers[$key] = $this->consumeFromLayers($layers[$key], $toConsume);
                }

                continue;
            }

            $item = $event['sale_item'];
            $quantity = (float) $item->quantity_sold;
            if ($quantity <= 0) {
                continue;
            }
            $layerKey = $this->resolveLayerKey($layers, $item->product_id, $item->product->name ?? '');
            if ($layerKey === null) {
                continue;
            }
            [$layers[$layerKey], $cost] = $this->consumeFromLayersAndReturnCost($layers[$layerKey], $quantity);
            $costs[$item->id] = round($cost, 2);
        }

        return $costs;
    }

    /** Inventory may be tracked against a product id or, when unlinked, against an item name. */
    protected function resolveLayerKey(array $layers, $productId, string $productName): ?string
    {
        $byId = $productId ? (string) $productId : null;
        if ($byId !== null && isset($layers[$byId])) {
            return $byId;
        }
        $byName = 'name:'.$productName;

        return isset($layers[$byName]) ? $byName : null;
    }

    /**
     * Consume quantity from layers (FIFO), return remaining layers.
     */
    protected function consumeFromLayers(array $layers, float $quantity): array
    {
        $remaining = $quantity;
        $newLayers = [];
        foreach ($layers as [$qty, $cost]) {
            if ($remaining <= 0) {
                $newLayers[] = [$qty, $cost];

                continue;
            }
            if ($remaining >= $qty) {
                $remaining -= $qty;

                continue;
            }
            $newLayers[] = [$qty - $remaining, $cost];
            $remaining = 0;
        }

        return $newLayers;
    }

    /**
     * Consume quantity from layers; return [newLayers, cost].
     */
    protected function consumeFromLayersAndReturnCost(array $layers, float $quantity): array
    {
        $remaining = $quantity;
        $cost = 0;
        $newLayers = [];
        foreach ($layers as [$qty, $unitCost]) {
            if ($remaining <= 0) {
                $newLayers[] = [$qty, $unitCost];

                continue;
            }
            if ($remaining >= $qty) {
                $cost += $qty * $unitCost;
                $remaining -= $qty;

                continue;
            }
            $take = $remaining;
            $cost += $take * $unitCost;
            $newLayers[] = [$qty - $take, $unitCost];
            $remaining = 0;
        }

        return [$newLayers, $cost];
    }

    protected function itemKey(InventoryRecord $record): string
    {
        if ($record->product_id) {
            return (string) $record->product_id;
        }

        return 'name:'.($record->item_name ?? '');
    }
}
