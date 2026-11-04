@if (($pendingOnlineOrders ?? 0) > 0)
            <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                <strong>{{ $pendingOnlineOrders }}</strong> website order request{{ $pendingOnlineOrders === 1 ? '' : 's' }} awaiting payment (channel: Online Store).
                <a href="{{ route('admin.ecommerce.sales', ['module' => 'orders', 'channel' => 'online', 'payment' => 'Pending']) }}" class="ml-1 font-semibold underline">View them</a>
            </div>
        @endif

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
                        placeholder="Order ID, name, phone, email…"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                </div>
                <div class="w-full max-w-[200px]">
                    <label for="channel" class="mb-1 block text-sm font-medium text-slate-700">Channel</label>
                    <select
                        id="channel"
                        name="channel"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                        <option value="all" @selected(($channel ?? 'all') === 'all')>All channels</option>
                        <option value="online" @selected(($channel ?? 'all') === 'online')>Online Store (website)</option>
                        <option value="other" @selected(($channel ?? 'all') === 'other')>Other channels</option>
                    </select>
                </div>
                <div class="w-full max-w-[200px]">
                    <label for="payment" class="mb-1 block text-sm font-medium text-slate-700">Payment</label>
                    <select
                        id="payment"
                        name="payment"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                        <option value="all" @selected($payment === 'all')>All</option>
                        <option value="Paid" @selected($payment === 'Paid')>Paid</option>
                        <option value="Pending" @selected($payment === 'Pending')>Pending</option>
                        <option value="Credit" @selected($payment === 'Credit')>Credit</option>
                    </select>
                </div>
                <div class="w-full max-w-[200px]">
                    <label for="delivery" class="mb-1 block text-sm font-medium text-slate-700">Delivery</label>
                    <select
                        id="delivery"
                        name="delivery"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                        <option value="all" @selected($delivery === 'all')>All</option>
                        <option value="Delivered" @selected($delivery === 'Delivered')>Delivered</option>
                        <option value="Pending" @selected($delivery === 'Pending')>Pending</option>
                        <option value="Returned" @selected($delivery === 'Returned')>Returned</option>
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
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.orders', array_merge(request()->query(), ['sort' => 'sale_date', 'direction' => $sort === 'sale_date' ? $nextDirection : 'desc'])) }}">Date</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.orders', array_merge(request()->query(), ['sort' => 'sales_id', 'direction' => $sort === 'sales_id' ? $nextDirection : 'asc'])) }}">Order ID</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.orders', array_merge(request()->query(), ['sort' => 'customer_name', 'direction' => $sort === 'customer_name' ? $nextDirection : 'asc'])) }}">Customer</a>
                        </th>
                        <th>Channel</th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.orders', array_merge(request()->query(), ['sort' => 'items_count', 'direction' => $sort === 'items_count' ? $nextDirection : 'desc'])) }}">Items</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.orders', array_merge(request()->query(), ['sort' => 'order_total', 'direction' => $sort === 'order_total' ? $nextDirection : 'desc'])) }}">Order Total</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.orders', array_merge(request()->query(), ['sort' => 'payment_status', 'direction' => $sort === 'payment_status' ? $nextDirection : 'asc'])) }}">Payment</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.orders', array_merge(request()->query(), ['sort' => 'delivery_status', 'direction' => $sort === 'delivery_status' ? $nextDirection : 'asc'])) }}">Delivery</a>
                        </th>
                        <th class="admin-table-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($orders as $order)
                        <tr>
                            <td>{{ $order->sale_date }}</td>
                            <td>{{ $order->sales_id }}</td>
                            <td>
                                <div>{{ $order->customer_name }}</div>
                                @if ($order->barcode)
                                    <div class="text-xs text-slate-500">{{ $order->barcode }}</div>
                                @endif
                            </td>
                            <td>
                                @if ($order->sales_channel === 'Online Store')
                                    <span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-800 ring-1 ring-sky-200">Online Store</span>
                                @else
                                    {{ $order->sales_channel ?: '—' }}
                                @endif
                            </td>
                            <td>{{ number_format((float) $order->items_count, 0) }}</td>
                            <td>{{ number_format((float) $order->order_total, 2) }}</td>
                            <td><x-admin.sale-status-badge :status="$order->payment_status" type="payment" /></td>
                            <td><x-admin.sale-status-badge :status="$order->delivery_status" type="delivery" /></td>
                            <td class="admin-table-td-actions">
                                <x-admin.table-actions>
                                    <x-admin.table-action :href="route('admin.sales.show', $order->id)">View</x-admin.table-action>
                                </x-admin.table-actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="admin-table-empty">No orders found. Website checkout orders appear here with channel <strong>Online Store</strong> (also under Core → Sales).</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>

        <x-admin.table-pagination :paginator="$orders" label="orders" />
