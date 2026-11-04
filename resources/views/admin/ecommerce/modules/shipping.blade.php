<div class="grid gap-4 md:grid-cols-3">
            @forelse ($channelStats as $stat)
                <article class="rounded-2xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $stat->sales_channel }}</p>
                    <div class="mt-3 space-y-1 text-sm text-slate-700">
                        <p>Total Orders: {{ number_format((float) $stat->total_orders, 0) }}</p>
                        <p>Pending: {{ number_format((float) $stat->pending_orders, 0) }}</p>
                        <p>Delivered: {{ number_format((float) $stat->delivered_orders, 0) }}</p>
                        <p>Returned: {{ number_format((float) $stat->returned_orders, 0) }}</p>
                    </div>
                </article>
            @empty
                <article class="rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-500">
                    No channel shipping stats available yet.
                </article>
            @endforelse
        </div>

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
                        placeholder="Order ID, customer, channel..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                </div>
                <div class="w-full max-w-[230px]">
                    <label for="scope" class="mb-1 block text-sm font-medium text-slate-700">Scope</label>
                    <select
                        id="scope"
                        name="scope"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                        <option value="all" @selected($scope === 'all')>All shipments</option>
                        <option value="pending_dispatch" @selected($scope === 'pending_dispatch')>Pending dispatch</option>
                        <option value="delivered" @selected($scope === 'delivered')>Delivered</option>
                        <option value="returned" @selected($scope === 'returned')>Returned</option>
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
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.fulfillment.shipping', array_merge(request()->query(), ['sort' => 'sale_date', 'direction' => $sort === 'sale_date' ? $nextDirection : 'desc'])) }}">Date</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.fulfillment.shipping', array_merge(request()->query(), ['sort' => 'sales_id', 'direction' => $sort === 'sales_id' ? $nextDirection : 'asc'])) }}">Order ID</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.fulfillment.shipping', array_merge(request()->query(), ['sort' => 'customer_name', 'direction' => $sort === 'customer_name' ? $nextDirection : 'asc'])) }}">Customer</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.fulfillment.shipping', array_merge(request()->query(), ['sort' => 'sales_channel', 'direction' => $sort === 'sales_channel' ? $nextDirection : 'asc'])) }}">Channel</a>
                        </th>
                        <th>Payment</th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.fulfillment.shipping', array_merge(request()->query(), ['sort' => 'delivery_status', 'direction' => $sort === 'delivery_status' ? $nextDirection : 'asc'])) }}">Delivery</a>
                        </th>
                        <th>Dispatch Priority</th>
                        <th>Workflow Step</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($shipments as $shipment)
                        <tr>
                            <td>{{ $shipment->sale_date }}</td>
                            <td>{{ $shipment->sales_id }}</td>
                            <td>{{ $shipment->customer_name }}</td>
                            <td>{{ $shipment->sales_channel ?: '-' }}</td>
                            <td><x-admin.sale-status-badge :status="$shipment->payment_status" type="payment" /></td>
                            <td><x-admin.sale-status-badge :status="$shipment->delivery_status" type="delivery" /></td>
                            <td>{{ $shipment->dispatch_priority }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $shipment->workflow_step }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="admin-table-empty">No shipping records found.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>

        <x-admin.table-pagination :paginator="$shipments" label="shipments" />
