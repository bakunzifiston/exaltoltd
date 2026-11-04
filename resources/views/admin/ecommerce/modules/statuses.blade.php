<form method="GET" class="admin-filter-panel">
            <input type="hidden" name="module" value="{{ $hubModule }}">
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-full max-w-sm">
                    <label for="search" class="mb-1 block text-sm font-medium text-slate-700">Search</label>
                    <input
                        id="search"
                        name="search"
                        type="text"
                        value="{{ $search }}"
                        placeholder="Order ID, customer, invoice..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                </div>
                <div class="w-full max-w-[240px]">
                    <label for="stage" class="mb-1 block text-sm font-medium text-slate-700">Stage Filter</label>
                    <select
                        id="stage"
                        name="stage"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                        <option value="all" @selected($stage === 'all')>All stages</option>
                        <option value="processing" @selected($stage === 'processing')>Processing</option>
                        <option value="completed" @selected($stage === 'completed')>Completed</option>
                        <option value="at_risk" @selected($stage === 'at_risk')>At risk / exception</option>
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
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.statuses', array_merge(request()->query(), ['sort' => 'sale_date', 'direction' => $sort === 'sale_date' ? $nextDirection : 'desc'])) }}">Date</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.statuses', array_merge(request()->query(), ['sort' => 'sales_id', 'direction' => $sort === 'sales_id' ? $nextDirection : 'asc'])) }}">Order ID</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.statuses', array_merge(request()->query(), ['sort' => 'customer_name', 'direction' => $sort === 'customer_name' ? $nextDirection : 'asc'])) }}">Customer</a>
                        </th>
                        <th>Payment</th>
                        <th>Delivery</th>
                        <th>Stage</th>
                        <th>Aging (days)</th>
                        <th>Timeline Note</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($orders as $order)
                        <tr>
                            <td>{{ $order->sale_date }}</td>
                            <td>{{ $order->sales_id }}</td>
                            <td>{{ $order->customer_name }}</td>
                            <td><x-admin.sale-status-badge :status="$order->payment_status" type="payment" /></td>
                            <td><x-admin.sale-status-badge :status="$order->delivery_status" type="delivery" /></td>
                            <td>{{ $order->status_stage }}</td>
                            <td>{{ $order->aging_days }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $order->status_note }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="admin-table-empty">No status records found.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>

        <x-admin.table-pagination :paginator="$orders" label="orders" />
