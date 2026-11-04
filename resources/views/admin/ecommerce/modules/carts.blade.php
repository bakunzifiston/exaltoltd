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
                        placeholder="Customer, sale ID, invoice..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                </div>
                <div class="w-full max-w-[220px]">
                    <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Cart Status</label>
                    <select
                        id="status"
                        name="status"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                        <option value="all" @selected($status === 'all')>All</option>
                        <option value="active" @selected($status === 'active')>Active checkout</option>
                        <option value="checked_out" @selected($status === 'checked_out')>Checked out</option>
                        <option value="returned" @selected($status === 'returned')>Returned flow</option>
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
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.carts', array_merge(request()->query(), ['sort' => 'sale_date', 'direction' => $sort === 'sale_date' ? $nextDirection : 'desc'])) }}">Sale Date</a>
                        </th>
                        <th>Sale ID</th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.carts', array_merge(request()->query(), ['sort' => 'customer_name', 'direction' => $sort === 'customer_name' ? $nextDirection : 'asc'])) }}">Customer</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.carts', array_merge(request()->query(), ['sort' => 'items_count', 'direction' => $sort === 'items_count' ? $nextDirection : 'desc'])) }}">Items</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.carts', array_merge(request()->query(), ['sort' => 'cart_total', 'direction' => $sort === 'cart_total' ? $nextDirection : 'desc'])) }}">Cart Total</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.carts', array_merge(request()->query(), ['sort' => 'payment_status', 'direction' => $sort === 'payment_status' ? $nextDirection : 'asc'])) }}">Payment</a>
                        </th>
                        <th>Delivery</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($carts as $cart)
                        <tr>
                            <td>{{ $cart->sale_date }}</td>
                            <td>{{ $cart->sales_id }}</td>
                            <td>{{ $cart->customer_name }}</td>
                            <td>{{ number_format((float) $cart->items_count, 0) }}</td>
                            <td>{{ number_format((float) $cart->cart_total, 2) }}</td>
                            <td><x-admin.sale-status-badge :status="$cart->payment_status" type="payment" /></td>
                            <td><x-admin.sale-status-badge :status="$cart->delivery_status" type="delivery" /></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="admin-table-empty">No cart records found.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>

        <x-admin.table-pagination :paginator="$carts" label="carts" />
