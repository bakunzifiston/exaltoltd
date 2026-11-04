<form method="GET" class="admin-filter-panel">
            <input type="hidden" name="module" value="{{ $hubModule }}">
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-full max-w-sm">
                    <label for="search" class="mb-1 block text-sm font-medium text-slate-700">Search customers</label>
                    <input
                        id="search"
                        name="search"
                        type="text"
                        value="{{ $search }}"
                        placeholder="Customer name or phone..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
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
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.customers.profiles', array_merge(request()->query(), ['sort' => 'customer_name', 'direction' => $sort === 'customer_name' ? $nextDirection : 'asc'])) }}">Customer</a>
                        </th>
                        <th>Phone</th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.customers.profiles', array_merge(request()->query(), ['sort' => 'orders_count', 'direction' => $sort === 'orders_count' ? $nextDirection : 'desc'])) }}">Orders</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.customers.profiles', array_merge(request()->query(), ['sort' => 'total_revenue', 'direction' => $sort === 'total_revenue' ? $nextDirection : 'desc'])) }}">Total Revenue</a>
                        </th>
                        <th>Paid Revenue</th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.customers.profiles', array_merge(request()->query(), ['sort' => 'last_sale_date', 'direction' => $sort === 'last_sale_date' ? $nextDirection : 'desc'])) }}">Last Sale</a>
                        </th>
                        <th>Account Link</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $customer)
                        <tr>
                            <td>{{ $customer->customer_name }}</td>
                            <td>{{ $customer->customer_phone }}</td>
                            <td>{{ number_format((float) $customer->orders_count, 0) }}</td>
                            <td>{{ number_format((float) $customer->total_revenue, 2) }}</td>
                            <td>{{ number_format((float) $customer->paid_revenue, 2) }}</td>
                            <td>{{ $customer->last_sale_date }}</td>
                            <td>
                                @if ($customer->has_user_account)
                                    <a href="{{ route('admin.users.show', $customer->user_id) }}" class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">
                                        Linked User
                                    </a>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-200">
                                        External Customer
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="admin-table-empty">No customers found.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>

        <x-admin.table-pagination :paginator="$customers" label="customers" />
