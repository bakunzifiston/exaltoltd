<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesProduction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductionRequest;
use App\Http\Requests\Admin\UpdateProductionRequest;
use App\Models\Employee;
use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionSample;
use App\Models\SaleItem;
use App\Services\ProductionCostingService;
use App\Services\TraceabilityService;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductionController extends Controller
{
    use AuthorizesProduction;

    public function index(Request $request): View
    {
        $search = (string) $request->string('search', '');
        $sort = in_array($request->query('sort'), ['batch_id', 'quantity_produced', 'damaged', 'production_date', 'created_at'], true)
            ? $request->query('sort')
            : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $productions = Production::query()
            ->with('product')
            ->unless($request->user()->can('manage-all-production'),
                fn ($query) => $query->where('created_by', $request->user()->id))
            // Grouped, so a search can never widen the own-batches restriction above.
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('batch_id', 'like', '%'.$search.'%')
                        ->orWhere('responsible_staff', 'like', '%'.$search.'%')
                        ->orWhere('barcode', 'like', '%'.$search.'%')
                        ->orWhereIn('id', $this->batchesUsingLot($search));
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        // Name every lot the listed batches drew from in one query, not one per material row.
        $lotIds = $productions->getCollection()
            ->flatMap(fn (Production $p) => array_column(Production::normalizeMaterials($p->inventory_record_id), 'inventory_id'))
            ->filter()->unique()->values();
        $lotNames = InventoryRecord::withTrashed()->whereIn('id', $lotIds)->pluck('item_name', 'id');

        return view('admin.productions.index', compact('productions', 'search', 'sort', 'direction', 'lotNames'));
    }

    public function create(): View
    {
        $products = Product::orderBy('type')->pluck('type', 'id');
        $productBarcodes = Product::orderBy('type')->pluck('barcode', 'id');
        $productShelfLife = Product::whereNotNull('shelf_life_days')->pluck('shelf_life_days', 'id');
        $inventoryOptions = $this->inventoryOptions();

        $employees = Employee::query()->orderBy('name')->get(['id', 'name', 'position']);

        return view('admin.productions.create', compact('products', 'productBarcodes', 'productShelfLife', 'inventoryOptions', 'employees'));
    }

    public function store(StoreProductionRequest $request): RedirectResponse
    {
        // One transaction: if material deduction fails, the batch insert rolls back too (no orphan).
        DB::transaction(fn () => Production::create($request->validated()));

        return redirect()->route('admin.productions.index')->with('status', 'Production created successfully.');
    }

    public const TABS = ['overview' => 'Overview', 'traceability' => 'Traceability', 'quality' => 'Quality & release', 'label' => 'Label', 'incidents' => 'Incidents'];

    public function show(Request $request, Production $production, TraceabilityService $trace): View
    {
        $this->authorizeProduction($production);
        $production->load(['product', 'releasedBy']);

        $tab = (string) $request->query('tab', 'overview');
        if (! array_key_exists($tab, self::TABS)) {
            $tab = 'overview';
        }

        $data = ['production' => $production, 'tab' => $tab, 'tabs' => self::TABS];

        if ($tab === 'overview') {
            // Resolve consumed raw materials once (no N+1), with per-line cost.
            $costs = app(ProductionCostingService::class)->lotUnitCosts();
            $raw = Production::normalizeMaterials($production->inventory_record_id);
            $records = InventoryRecord::withTrashed()->whereIn('id', collect($raw)->pluck('inventory_id')->filter()->all())
                ->get()
                ->keyBy('id');

            $data['materials'] = collect($raw)->map(function ($item) use ($records, $costs) {
                $record = $records[$item['inventory_id'] ?? null] ?? null;
                $qty = (float) ($item['quantity_used'] ?? 0);
                $unitCost = $costs[$item['inventory_id'] ?? null] ?? null;

                return [
                    'record' => $record,
                    'name' => $record?->item_name ?? 'Unknown',
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'line_cost' => $unitCost !== null ? round($qty * $unitCost, 2) : null,
                ];
            });
            $data['materialsCost'] = $data['materials']->sum(fn ($m) => $m['line_cost'] ?? 0);
        }

        if (in_array($tab, ['overview', 'traceability'], true)) {
            $data['forward'] = $trace->forward($production);
            $data['summary'] = $trace->summary($production, $data['forward']);
        }

        if ($tab === 'traceability') {
            $data['backward'] = $trace->backward($production);
            $data['timeline'] = $trace->timeline($production);
        }

        if ($tab === 'quality') {
            $data['checks'] = $production->qualityChecks()->with('checker:id,name')->get();
        }

        if ($tab === 'incidents') {
            $data['incidents'] = $production->incidents()->with('creator:id,name')->get();
        }

        return view('admin.productions.show', $data);
    }

    public function edit(Production $production): View
    {
        $this->authorizeProduction($production);
        $products = Product::orderBy('type')->pluck('type', 'id');
        $productBarcodes = Product::orderBy('type')->pluck('barcode', 'id');
        $productShelfLife = Product::whereNotNull('shelf_life_days')->pluck('shelf_life_days', 'id');
        $inventoryOptions = $this->inventoryOptions($production);

        $employees = Employee::query()->orderBy('name')->get(['id', 'name', 'position']);

        return view('admin.productions.edit', compact('production', 'products', 'productBarcodes', 'productShelfLife', 'inventoryOptions', 'employees'));
    }

    public function update(UpdateProductionRequest $request, Production $production): RedirectResponse
    {
        $this->authorizeProduction($production);
        DB::transaction(fn () => $production->update($request->validated()));

        return redirect()->route('admin.productions.show', $production)->with('status', 'Production updated successfully.');
    }

    public function destroy(Request $request, Production $production): RedirectResponse
    {
        abort_unless($request->user()->can('delete-records'), 403);

        $lineIds = SaleItem::query()->where('production_id', $production->id)->pluck('id');
        $samples = ProductionSample::query()->where('production_id', $production->id)->get();

        // Customer returns point at the sale lines themselves, so those can never be removed.
        if ($lineIds->isNotEmpty() && DB::table('stock_return_items')->whereIn('sale_item_id', $lineIds)->exists()) {
            return back()->withErrors(['production' => 'Stock returns are recorded against sales made from batch '.$production->batch_id.', so it cannot be deleted.']);
        }

        // Sale lines and free samples taken from this batch would go with it, so ask first.
        if (($lineIds->isNotEmpty() || $samples->isNotEmpty()) && ! $request->boolean('force')) {
            $sales = SaleItem::query()->whereIn('id', $lineIds)->distinct()->count('sale_id');
            $parts = [];
            if ($lineIds->isNotEmpty()) {
                $parts[] = $lineIds->count().' sale line'.($lineIds->count() === 1 ? '' : 's').' in '.$sales.' sale'.($sales === 1 ? '' : 's').' (those sales keep their recorded revenue but lose these lines, and their cost of goods)';
            }
            if ($samples->isNotEmpty()) {
                $parts[] = $samples->count().' production sample'.($samples->count() === 1 ? '' : 's').' ('.$samples->pluck('reference')->implode(', ').')';
            }

            return back()->with('force_prompt', [
                'module' => 'productions',
                'label' => 'productions',
                'ids' => [$production->id],
                'items' => [[
                    'name' => $production->batch_id.' (#'.$production->id.')',
                    'detail' => 'Deleting this batch will also permanently delete '.implode(' and ', $parts).'. Its raw materials go back to stock.',
                ]],
            ]);
        }

        DB::transaction(function () use ($production, $samples, $lineIds): void {
            // The batch is going, so a sample's units are not put back; its documents are removed.
            $samples->each(function (ProductionSample $sample): void {
                $sample->documents()->get()->each->delete();
                $sample->delete();
            });
            SaleItem::query()->whereIn('id', $lineIds)->delete();
            $production->delete();
        });

        return redirect()->route('admin.productions.index')->with('status', 'Production deleted successfully.');
    }

    /** Batches made from a delivery whose lot or supplier lot number matches a search. */
    private function batchesUsingLot(string $search): array
    {
        $lotIds = InventoryRecord::withTrashed()
            ->where(fn ($q) => $q->where('lot_number', 'like', '%'.$search.'%')->orWhere('supplier_lot_number', 'like', '%'.$search.'%'))
            ->pluck('id')->all();

        return $lotIds === [] ? [] : collect(app(ProductionCostingService::class)->consumption())
            ->whereIn('inventory_id', $lotIds)->pluck('production_id')->unique()->values()->all();
    }

    /**
     * Raw-material lots available to consume, keyed by id, with the remaining on-hand
     * quantity baked into the label. Depleted lots (0 remaining) are hidden so you can
     * only pick stock that actually exists — except any lot already attached to the
     * batch being edited, which is kept so its existing row still resolves.
     *
     * @return array<int|string, string>
     */
    private function inventoryOptions(?Production $production = null): array
    {
        $keepIds = collect(Production::normalizeMaterials($production?->inventory_record_id ?? []))
            ->pluck('inventory_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        return InventoryRecord::query()
            ->orderBy('item_name')
            ->orderBy('record_date')
            ->get(['id', 'item_name', 'lot_number', 'quantity_in', 'quantity_out', 'inspection_status'])
            // Rejected deliveries can never go into a batch; empty ones only if already in it.
            ->filter(fn (InventoryRecord $record) => in_array((int) $record->id, $keepIds, true)
                || ($record->remainingQuantity() > 0 && $record->inspection_status !== 'rejected'))
            ->mapWithKeys(function (InventoryRecord $record) {
                $remaining = rtrim(rtrim(number_format($record->remainingQuantity(), 2), '0'), '.');
                $name = $record->item_name;
                if ($record->lot_number) {
                    $name .= ' · '.$record->lot_number;
                }

                $pending = $record->inspection_status === 'pending' ? ' (awaiting inspection)' : '';

                return [$record->id => "{$name} — {$remaining} in stock{$pending}"];
            })
            ->all();
    }
}
