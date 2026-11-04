<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInventoryRecordRequest;
use App\Http\Requests\Admin\UpdateInventoryRecordRequest;
use App\Models\InventoryRecord;
use App\Models\Product;
use App\Support\TablePageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryRecordController extends Controller
{
    public function index(Request $request): View
    {
        $search = (string) $request->string('search', '');
        $sort = in_array($request->query('sort'), ['supplier_name', 'quantity_in', 'quantity_out', 'total_amount', 'record_date'], true)
            ? $request->query('sort')
            : 'record_date';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $records = InventoryRecord::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('supplier_name', 'like', '%'.$search.'%')
                        ->orWhere('invoice_number', 'like', '%'.$search.'%')
                        ->orWhere('item_name', 'like', '%'.$search.'%')
                        ->orWhere('storage_location', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('item_type'), fn ($query) => $query->where('item_type', $request->string('item_type')))
            ->orderBy($sort, $direction)
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        return view('admin.inventory-records.index', compact('records', 'search', 'sort', 'direction'));
    }

    public function create(): View
    {
        $products = Product::orderBy('name')->pluck('name', 'id');

        return view('admin.inventory-records.create', compact('products'));
    }

    public function store(StoreInventoryRecordRequest $request): RedirectResponse
    {
        $data = $this->normalizeData($request->validated(), calculateTotal: true);
        InventoryRecord::create($data);

        return redirect()->route('admin.inventory-records.index')->with('status', 'Inventory record created successfully.');
    }

    public function show(InventoryRecord $inventoryRecord): View
    {
        $inventoryRecord->load('creator:id,name');

        $movements = $inventoryRecord->movements()
            ->with(['production:id,batch_id', 'user:id,name'])
            ->orderBy('moved_at')
            ->orderBy('id')
            ->get();

        // Running balance tells the story of the lot from intake to now.
        $running = 0.0;
        foreach ($movements as $movement) {
            $running = round($running + (float) $movement->quantity, 2);
            $movement->running_balance = $running;
        }

        $consumed = (float) $movements->where('type', 'consumption')->sum('quantity') * -1;

        return view('admin.inventory-records.show', compact('inventoryRecord', 'movements', 'consumed'));
    }

    public function edit(InventoryRecord $inventoryRecord): View
    {
        $products = Product::orderBy('name')->pluck('name', 'id');

        return view('admin.inventory-records.edit', compact('inventoryRecord', 'products'));
    }

    public function update(UpdateInventoryRecordRequest $request, InventoryRecord $inventoryRecord): RedirectResponse
    {
        $data = $this->normalizeData($request->validated());
        $inventoryRecord->update($data);

        return redirect()->route('admin.inventory-records.show', $inventoryRecord)->with('status', 'Inventory record updated successfully.');
    }

    public function destroy(InventoryRecord $inventoryRecord): RedirectResponse
    {
        // Deleting financial/stock records is reserved for owner/manager.
        abort_unless(request()->user()->can('delete-records'), 403);

        // A consumed delivery underpins FIFO cost history — never let it be removed.
        if ($inventoryRecord->hasBeenConsumed()) {
            return back()->withErrors([
                'inventory' => 'This delivery has already been used in production, so it cannot be deleted (it underpins stock-cost history). Adjust quantities instead.',
            ]);
        }

        $inventoryRecord->delete();

        return redirect()->route('admin.inventory-records.index')->with('status', 'Inventory record deleted successfully.');
    }

    /**
     * "Stock on hand" by item: each material with its open lots, value, and low-stock / expiry flags.
     */
    public function stockOnHand(Request $request): View
    {
        $groups = InventoryRecord::query()
            ->orderBy('item_name')
            ->orderBy('record_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (InventoryRecord $r) => $r->item_name)
            ->map(function ($lots, $name) {
                $openLots = $lots->filter(fn (InventoryRecord $r) => $r->remainingQuantity() > 0.0001)->values();
                $reorder = (float) $lots->max('reorder_level');
                $totalRemaining = (float) $lots->sum(fn (InventoryRecord $r) => $r->remainingQuantity());

                return [
                    'name' => $name,
                    'item_type' => $lots->first()->item_type,
                    'lots' => $openLots,
                    'total_remaining' => $totalRemaining,
                    'total_value' => (float) $lots->sum(fn (InventoryRecord $r) => $r->line_value),
                    'reorder_level' => $reorder,
                    'low_stock' => $reorder > 0 && $totalRemaining <= $reorder,
                    'has_expiry_alert' => $openLots->contains(fn (InventoryRecord $r) => $r->is_expired || $r->is_near_expiry),
                ];
            })
            ->filter(fn ($g) => $g['lots']->isNotEmpty() || $g['low_stock'])
            ->sortBy('name')
            ->values();

        $summary = [
            'items' => $groups->count(),
            'total_value' => $groups->sum('total_value'),
            'low_stock' => $groups->where('low_stock', true)->count(),
            'expiry_alerts' => $groups->where('has_expiry_alert', true)->count(),
        ];

        // Summary totals stay over the whole set; only the listing is paged.
        return view('admin.inventory-records.stock', [
            'groups' => TablePageSize::paginate($groups, $request, 25),
            'summary' => $summary,
        ]);
    }

    public function itemNameSuggestions(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        $names = InventoryRecord::query()
            ->where('item_type', 'Raw Material')
            ->when($q !== '', fn ($query) => $query->where('item_name', 'like', '%'.$q.'%'))
            ->distinct()
            ->orderBy('item_name')
            ->pluck('item_name');

        return response()->json($names);
    }

    public function supplierNameSuggestions(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        $names = InventoryRecord::query()
            ->when($q !== '', fn ($query) => $query->where('supplier_name', 'like', '%'.$q.'%'))
            ->distinct()
            ->orderBy('supplier_name')
            ->pluck('supplier_name');

        return response()->json($names);
    }

    private function normalizeData(array $data, bool $calculateTotal = false): array
    {
        // Left unset, the column default (approved) applies, as for every older delivery.
        if (empty($data['inspection_status'])) {
            unset($data['inspection_status']);
        }

        if ($calculateTotal) {
            $onHand = max(0, (float) $data['quantity_in'] - (float) $data['quantity_out']);
            $data['unit_cost'] = round((float) $data['unit_cost'], 2);
            $data['total_amount'] = round($onHand * $data['unit_cost'], 2);
        }
        $total = (float) ($data['total_amount'] ?? 0);
        $paid = (float) ($data['amount_paid'] ?? 0);
        $qty = (float) ($data['quantity_in'] ?? 0);

        if (! $calculateTotal && $qty > 0 && $total > 0) {
            $data['unit_cost'] = round($total / $qty, 2);
        }

        $data['payment_status'] = match (true) {
            $total <= 0 => 'Unpaid',
            $paid >= $total => 'Paid',
            $paid > 0 => 'Partial',
            default => 'Unpaid',
        };

        if (($data['item_type'] ?? null) !== 'Product') {
            $data['product_id'] = null;
        }

        return $data;
    }
}
