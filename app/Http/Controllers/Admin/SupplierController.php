<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryRecord;
use App\Models\Supplier;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $search = (string) $request->string('search', '');
        $sort = $request->query('sort', 'total_purchased');
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $suppliers = Supplier::query()
            ->withCount('inventoryRecords')
            ->withSum('inventoryRecords as total_purchased', 'total_amount')
            ->withSum('inventoryRecords as total_paid', 'amount_paid')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when(
                $sort === 'total_owed',
                fn ($q) => $q->orderByRaw(
                    'COALESCE((SELECT SUM(COALESCE(total_amount,0) - COALESCE(amount_paid,0)) FROM inventory_records WHERE inventory_records.supplier_id = suppliers.id), 0) '.$direction
                ),
                fn ($q) => $q->orderByDesc('total_purchased')
            )
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        $summary = [
            'total' => Supplier::count(),
            'payable' => (float) InventoryRecord::query()
                ->whereIn('payment_status', ['Unpaid', 'Partial'])
                ->selectRaw('COALESCE(SUM(COALESCE(total_amount, 0) - COALESCE(amount_paid, 0)), 0) as balance')
                ->value('balance'),
            'overdue_invoices' => InventoryRecord::query()
                ->whereIn('payment_status', ['Unpaid', 'Partial'])
                ->whereNotNull('payment_due_date')
                ->whereDate('payment_due_date', '<', now()->toDateString())
                ->count(),
        ];

        return view('admin.suppliers.index', compact('suppliers', 'search', 'summary', 'sort', 'direction'));
    }

    public function create(): View
    {
        return view('admin.suppliers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $supplier = Supplier::create($this->validated($request));

        return redirect()->route('admin.suppliers.show', $supplier)->with('status', 'Supplier created successfully.');
    }

    public function show(Request $request, Supplier $supplier): View
    {
        $records = $supplier->inventoryRecords()
            ->orderByDesc('record_date')
            ->orderByDesc('id')
            ->paginate(TablePageSize::resolve($request, 10))->withQueryString();

        $stats = [
            'deliveries' => $supplier->inventoryRecords()->count(),
            'total_purchased' => (float) $supplier->inventoryRecords()->sum('total_amount'),
            'total_paid' => (float) $supplier->inventoryRecords()->sum('amount_paid'),
            'damaged_units' => (float) $supplier->inventoryRecords()->sum('damaged'),
            'last_delivery' => $supplier->inventoryRecords()->max('record_date'),
            'overdue_invoices' => $supplier->inventoryRecords()
                ->whereIn('payment_status', ['Unpaid', 'Partial'])
                ->whereNotNull('payment_due_date')
                ->whereDate('payment_due_date', '<', now()->toDateString())
                ->count(),
        ];
        $stats['balance'] = max(0, round($stats['total_purchased'] - $stats['total_paid'], 2));

        return view('admin.suppliers.show', compact('supplier', 'records', 'stats'));
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request));

        return redirect()->route('admin.suppliers.show', $supplier)->with('status', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $supplier->delete(); // soft delete; inventory records keep their supplier_id

        return redirect()->route('admin.suppliers.index')->with('status', 'Supplier archived successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
