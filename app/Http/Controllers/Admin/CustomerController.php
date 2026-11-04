<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SalePayment;
use App\Support\TablePageSize;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = (string) $request->string('search', '');
        $sort = in_array($request->query('sort'), ['name', 'sales_count', 'lifetime_value', 'outstanding', 'last_purchase_at'], true)
            ? $request->query('sort')
            : 'lifetime_value';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $balance = $request->query('balance', '');

        $customers = Customer::query()
            ->withCount('sales')
            ->withSum('sales as lifetime_value', 'total_revenue')
            ->withSum('sales as total_paid', 'amount_paid')
            ->withMax('sales as last_purchase_at', 'sale_date')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($balance === 'due', fn ($q) => $q->whereRaw(
                'COALESCE((SELECT SUM(total_revenue - returned_amount - amount_paid) FROM sales WHERE sales.customer_id = customers.id), 0) > 0'
            ))
            ->when($sort === 'outstanding',
                fn ($q) => $q->orderByRaw('(COALESCE((SELECT SUM(total_revenue - returned_amount - amount_paid) FROM sales WHERE sales.customer_id = customers.id), 0)) '.$direction),
                fn ($q) => $q->orderBy($sort, $direction))
            ->paginate(TablePageSize::resolve($request, 15))
            ->withQueryString();

        $summary = [
            'total' => Customer::count(),
            'new_this_month' => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
            'outstanding' => (float) \App\Models\Sale::query()
                ->where('payment_status', '!=', 'Paid')
                ->selectRaw('COALESCE(SUM(total_revenue - returned_amount - amount_paid), 0) as balance')
                ->value('balance'),
        ];

        return view('admin.customers.index', compact('customers', 'search', 'sort', 'direction', 'summary', 'balance'));
    }

    public function create(): View
    {
        return view('admin.customers.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $customer = Customer::create($this->validated($request));

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Customer created successfully.');
    }

    public function show(Request $request, Customer $customer): View
    {
        $sales = $customer->sales()
            ->withCount('items')
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->paginate(TablePageSize::resolve($request, 10))->withQueryString();

        $stats = [
            'orders' => $customer->sales()->count(),
            'lifetime_value' => (float) $customer->sales()->sum('total_revenue'),
            'total_paid' => (float) $customer->sales()->sum('amount_paid'),
            'first_purchase' => $customer->sales()->min('sale_date'),
            'last_purchase' => $customer->sales()->max('sale_date'),
        ];
        $stats['outstanding'] = max(0, round($stats['lifetime_value'] - $stats['total_paid'], 2));
        $stats['avg_order_value'] = $stats['orders'] > 0 ? $stats['lifetime_value'] / $stats['orders'] : 0;

        $recentPayments = SalePayment::query()
            ->whereHas('sale', fn ($q) => $q->where('customer_id', $customer->id))
            ->with('sale:id,sales_id')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('admin.customers.show', compact('customer', 'sales', 'stats', 'recentPayments'));
    }

    public function edit(Customer $customer): View
    {
        return view('admin.customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->validated($request));

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete(); // soft delete; sales keep their customer_id

        return redirect()->route('admin.customers.index')->with('status', 'Customer archived successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
