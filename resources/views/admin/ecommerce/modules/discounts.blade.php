<div class="grid gap-4 md:grid-cols-4">
            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Orders</p>
                <p class="mt-2 text-xl font-semibold text-slate-900">{{ number_format((float) $kpis->orders_count, 0) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Gross Revenue</p>
                <p class="mt-2 text-xl font-semibold text-slate-900">{{ number_format((float) $kpis->gross_revenue, 2) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Avg Order Value</p>
                <p class="mt-2 text-xl font-semibold text-slate-900">{{ number_format((float) $kpis->avg_order_value, 2) }}</p>
            </article>
            <article class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Credit Orders</p>
                <p class="mt-2 text-xl font-semibold text-amber-700">{{ number_format((float) $kpis->credit_orders, 0) }}</p>
            </article>
        </div>

        <form method="GET" class="admin-filter-panel">
            <input type="hidden" name="module" value="{{ $hubModule }}">
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-full max-w-sm">
                    <label for="search" class="mb-1 block text-sm font-medium text-slate-700">Search channel</label>
                    <input
                        id="search"
                        name="search"
                        type="text"
                        value="{{ $search }}"
                        placeholder="Sales channel..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                </div>
                <div class="w-full max-w-[220px]">
                    <label for="focus" class="mb-1 block text-sm font-medium text-slate-700">Focus Segment</label>
                    <select
                        id="focus"
                        name="focus"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                        <option value="all" @selected($focus === 'all')>All</option>
                        <option value="credit_heavy" @selected($focus === 'credit_heavy')>Credit heavy</option>
                        <option value="high_value" @selected($focus === 'high_value')>High value</option>
                    </select>
                </div>
                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Apply
                </button>
            </div>
        </form>
        <x-admin.ecommerce-data-table>
                <thead>
                    @php($nextDirection = $direction === 'asc' ? 'desc' : 'asc')
                    <tr>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.discounts', array_merge(request()->query(), ['sort' => 'sales_channel', 'direction' => $sort === 'sales_channel' ? $nextDirection : 'asc'])) }}">Channel</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.discounts', array_merge(request()->query(), ['sort' => 'orders_count', 'direction' => $sort === 'orders_count' ? $nextDirection : 'desc'])) }}">Orders</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.discounts', array_merge(request()->query(), ['sort' => 'avg_order_value', 'direction' => $sort === 'avg_order_value' ? $nextDirection : 'desc'])) }}">Avg Order</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.discounts', array_merge(request()->query(), ['sort' => 'gross_revenue', 'direction' => $sort === 'gross_revenue' ? $nextDirection : 'desc'])) }}">Revenue</a>
                        </th>
                        <th>Credit Rate</th>
                        <th>Target</th>
                        <th>Coupon Suggestion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($segments as $segment)
                        <tr>
                            <td>{{ $segment->sales_channel }}</td>
                            <td>{{ number_format((float) $segment->orders_count, 0) }}</td>
                            <td>{{ number_format((float) $segment->avg_order_value, 2) }}</td>
                            <td>{{ number_format((float) $segment->gross_revenue, 2) }}</td>
                            <td>{{ number_format((float) $segment->credit_rate, 2) }}%</td>
                            <td>{{ $segment->target }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $segment->coupon_suggestion }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="admin-table-empty">No discount segment data found.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>
