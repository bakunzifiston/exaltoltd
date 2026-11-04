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
                        placeholder="Order ID, invoice, customer..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                </div>
                <div class="w-full max-w-[260px]">
                    <label for="scope" class="mb-1 block text-sm font-medium text-slate-700">Scope</label>
                    <select
                        id="scope"
                        name="scope"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                        <option value="all" @selected($scope === 'all')>All exceptions</option>
                        <option value="returned_only" @selected($scope === 'returned_only')>Returned only</option>
                        <option value="refund_candidates" @selected($scope === 'refund_candidates')>Refund candidates (paid + returned)</option>
                        <option value="credit_reversals" @selected($scope === 'credit_reversals')>Credit reversals (returned + pending/credit)</option>
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
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.returns', array_merge(request()->query(), ['sort' => 'sale_date', 'direction' => $sort === 'sale_date' ? $nextDirection : 'desc'])) }}">Date</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.returns', array_merge(request()->query(), ['sort' => 'sales_id', 'direction' => $sort === 'sales_id' ? $nextDirection : 'asc'])) }}">Order ID</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.returns', array_merge(request()->query(), ['sort' => 'customer_name', 'direction' => $sort === 'customer_name' ? $nextDirection : 'asc'])) }}">Customer</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.returns', array_merge(request()->query(), ['sort' => 'total_revenue', 'direction' => $sort === 'total_revenue' ? $nextDirection : 'desc'])) }}">Order Total</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.returns', array_merge(request()->query(), ['sort' => 'delivery_status', 'direction' => $sort === 'delivery_status' ? $nextDirection : 'asc'])) }}">Delivery</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.sales.returns', array_merge(request()->query(), ['sort' => 'payment_status', 'direction' => $sort === 'payment_status' ? $nextDirection : 'asc'])) }}">Payment</a>
                        </th>
                        <th>Recommended Action</th>
                        <th>Estimated Refund</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($returns as $return)
                        <tr>
                            <td>{{ $return->sale_date }}</td>
                            <td>{{ $return->sales_id }}</td>
                            <td>{{ $return->customer_name }}</td>
                            <td>{{ number_format((float) $return->total_revenue, 2) }}</td>
                            <td><x-admin.sale-status-badge :status="$return->delivery_status" type="delivery" /></td>
                            <td><x-admin.sale-status-badge :status="$return->payment_status" type="payment" /></td>
                            <td>{{ $return->recommended_action }}</td>
                            <td>{{ number_format((float) $return->estimated_refund, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="admin-table-empty">No return/refund records found.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>

        <x-admin.table-pagination :paginator="$returns" label="returns" />
