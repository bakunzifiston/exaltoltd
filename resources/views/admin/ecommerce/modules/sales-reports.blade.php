<form method="GET" class="admin-filter-panel">
    <input type="hidden" name="module" value="{{ $hubModule }}">
    <div class="flex flex-wrap items-end gap-3">
        <div class="w-full max-w-[180px]">
            <label for="period" class="mb-1 block text-sm font-medium text-slate-700">Period</label>
            <select
                id="period"
                name="period"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
            >
                <option value="7d" @selected($period === '7d')>Last 7 days</option>
                <option value="30d" @selected($period === '30d')>Last 30 days</option>
                <option value="90d" @selected($period === '90d')>Last 90 days</option>
                <option value="365d" @selected($period === '365d')>Last 12 months</option>
            </select>
        </div>
        <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
            Apply
        </button>
        <p class="text-xs text-slate-500">Range: {{ $startDate }} to {{ $endDate }}</p>
    </div>
</form>

<div class="grid gap-4 md:grid-cols-5">
    <article class="rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Orders</p>
        <p class="mt-2 text-xl font-semibold text-slate-900">{{ number_format((float) $kpis->orders_count, 0) }}</p>
    </article>
    <article class="rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gross Revenue</p>
        <p class="mt-2 text-xl font-semibold text-slate-900">{{ number_format((float) $kpis->gross_revenue, 2) }}</p>
    </article>
    <article class="rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Order</p>
        <p class="mt-2 text-xl font-semibold text-slate-900">{{ number_format((float) $kpis->avg_order_value, 2) }}</p>
    </article>
    <article class="rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Paid Revenue</p>
        <p class="mt-2 text-xl font-semibold text-emerald-700">{{ number_format((float) $kpis->paid_revenue, 2) }}</p>
    </article>
    <article class="rounded-2xl border border-slate-200 bg-white p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Returned Orders</p>
        <p class="mt-2 text-xl font-semibold text-amber-700">{{ number_format((float) $kpis->returned_orders, 0) }}</p>
    </article>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <div class="admin-table-panel">
        <div class="border-b border-slate-200 px-4 py-3">
            <h3 class="text-sm font-semibold text-slate-900">Channel Performance</h3>
        </div>
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th>Channel</th>
                    <th class="admin-table-th-numeric">Orders</th>
                    <th class="admin-table-th-numeric">Revenue</th>
                    <th class="admin-table-th-numeric">Avg Order</th>
                    <th class="admin-table-th-numeric">Returns</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($channelPerformance as $row)
                    <tr>
                        <td>{{ $row->sales_channel }}</td>
                        <td class="admin-table-td-numeric">{{ number_format((float) $row->orders_count, 0) }}</td>
                        <td class="admin-table-td-numeric">{{ number_format((float) $row->revenue, 2) }}</td>
                        <td class="admin-table-td-numeric">{{ number_format((float) $row->avg_order_value, 2) }}</td>
                        <td class="admin-table-td-numeric">{{ number_format((float) $row->returned_orders, 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="admin-table-empty">No channel data found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="admin-table-panel">
        <div class="border-b border-slate-200 px-4 py-3">
            <h3 class="text-sm font-semibold text-slate-900">Top Products</h3>
        </div>
        <table class="admin-data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th class="admin-table-th-numeric">Units Sold</th>
                    <th class="admin-table-th-numeric">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($topProducts as $row)
                    <tr>
                        <td>{{ $row->product_name }}</td>
                        <td class="admin-table-td-numeric">{{ number_format((float) $row->units_sold, 2) }}</td>
                        <td class="admin-table-td-numeric">{{ number_format((float) $row->revenue, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="admin-table-empty">No product data found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="admin-table-panel">
    <div class="border-b border-slate-200 px-4 py-3">
        <h3 class="text-sm font-semibold text-slate-900">Payment Mix</h3>
    </div>
    <table class="admin-data-table">
        <thead>
            <tr>
                <th>Payment Status</th>
                <th class="admin-table-th-numeric">Orders</th>
                <th class="admin-table-th-numeric">Revenue</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($paymentMix as $row)
                <tr>
                    <td>{{ $row->payment_status }}</td>
                    <td class="admin-table-td-numeric">{{ number_format((float) $row->orders_count, 0) }}</td>
                    <td class="admin-table-td-numeric">{{ number_format((float) $row->revenue, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="admin-table-empty">No payment mix data found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
