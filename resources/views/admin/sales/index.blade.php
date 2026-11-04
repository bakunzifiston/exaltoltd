<x-layouts.admin title="Sales">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <div><h2 class="text-2xl font-semibold text-slate-900">Sales</h2><p class="mt-1 text-sm text-slate-500">Manage multi-item sales invoices.</p></div>
            <a href="{{ route('admin.sales.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">New Sale</a>
        </div>
        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        <form method="GET" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap gap-3">
                <input name="search" value="{{ $search }}" placeholder="Search sale/customer/invoice..." class="flex-1 min-w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="delivery_status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700">
                    <option value="">All Delivery Statuses</option>
                    @foreach(['Delivered','Pending','In Transit','Returned'] as $s)
                        <option value="{{ $s }}" @selected($deliveryStatus === $s)>{{ $s }}</option>
                    @endforeach
                </select>
                <select name="payment_status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700">
                    <option value="">All Payment Statuses</option>
                    @foreach(['Paid','Partially Paid','Pending','Credit', \App\Http\Controllers\Admin\SaleController::AWAITING_APPROVAL] as $s)
                        <option value="{{ $s }}" @selected($paymentStatus === $s)>{{ $s }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Apply</button>
                @if($search || $deliveryStatus || $paymentStatus)
                    <a href="{{ route('admin.sales.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Clear</a>
                @endif
            </div>
        </form>

        @if($deliveryStatus || $paymentStatus)
            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-teal-200 bg-teal-50 px-4 py-2 text-sm text-teal-800">
                <span class="font-medium">Filtered:</span>
                @if($deliveryStatus)
                    <span class="rounded-full bg-teal-100 px-2.5 py-0.5 text-xs font-semibold text-teal-800 ring-1 ring-teal-300">Delivery: {{ $deliveryStatus }}</span>
                @endif
                @if($paymentStatus)
                    <span class="rounded-full bg-teal-100 px-2.5 py-0.5 text-xs font-semibold text-teal-800 ring-1 ring-teal-300">Payment: {{ $paymentStatus }}</span>
                @endif
                <a href="{{ route('admin.sales.index') }}" class="ml-auto text-xs text-teal-600 hover:underline">Clear filters</a>
            </div>
        @endif
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">Sale ID</th><th class="px-3 py-2 text-left">Customer</th><th class="px-3 py-2 text-left">Channel</th><th class="px-3 py-2 text-left">Invoice</th><th class="px-3 py-2 text-left">Items</th><th class="px-3 py-2 text-left">Total Revenue</th><th class="px-3 py-2 text-left">Balance Due</th><th class="px-3 py-2 text-left">Payment</th><th class="px-3 py-2 text-left">Delivery</th><th class="px-3 py-2 text-left">Sale Date</th><th class="px-3 py-2 text-left">Actions</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($sales as $sale)
                        <tr>
                            <td class="px-3 py-2">{{ $sale->sales_id }}</td>
                            <td class="px-3 py-2">{{ $sale->customer_name }}</td>
                            <td class="px-3 py-2">
                                @if ($sale->sales_channel === 'Online Store')
                                    <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-800 ring-1 ring-sky-200">Online Store</span>
                                @else
                                    {{ $sale->sales_channel ?: '—' }}
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $sale->invoice_number }}</td>
                            <td class="px-3 py-2">{{ $sale->items_count }}</td>
                            <td class="px-3 py-2">{{ number_format($sale->total_revenue ?? 0, 0) }} RWF</td>
                            <td class="px-3 py-2">
                                @if ($sale->balance_due > 0)
                                    <span class="font-medium text-rose-700">{{ number_format($sale->balance_due, 0) }} RWF</span>
                                    @if ($sale->is_overdue)<span class="ml-1 rounded-full bg-rose-50 px-1.5 py-0.5 text-[11px] font-semibold text-rose-700 ring-1 ring-rose-200">overdue</span>@endif
                                @else
                                    <span class="text-emerald-700">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <x-admin.sale-status-badge :status="$sale->payment_status" type="payment" />
                                @if ($sale->pending_submissions_count > 0)
                                    <span class="mt-1 block w-fit rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-900 ring-1 ring-amber-200">awaiting approval</span>
                                @endif
                            </td>
                            <td class="px-3 py-2"><x-admin.sale-status-badge :status="$sale->delivery_status" type="delivery" /></td>
                            <td class="px-3 py-2">{{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('Y-m-d') }}</td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.sales.show', $sale) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                                    <a href="{{ route('admin.sales.edit', $sale) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                                    @can('delete-records')
                                        <form method="POST" action="{{ route('admin.sales.destroy', $sale) }}" onsubmit="return confirm('Delete this sale?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100">
                                                Delete
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-4 py-6 text-center text-slate-500">No sales found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$sales" label="sales" />
    </section>
</x-layouts.admin>
