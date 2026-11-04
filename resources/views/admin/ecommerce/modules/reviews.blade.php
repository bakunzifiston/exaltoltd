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
                        placeholder="Customer, phone, product..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                </div>
                <div class="w-full max-w-[220px]">
                    <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Queue Status</label>
                    <select
                        id="status"
                        name="status"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                        <option value="all" @selected($status === 'all')>All</option>
                        <option value="ready" @selected($status === 'ready')>Ready for review request</option>
                        <option value="pending" @selected($status === 'pending')>Pending delivery</option>
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
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.customers.reviews', array_merge(request()->query(), ['sort' => 'sale_date', 'direction' => $sort === 'sale_date' ? $nextDirection : 'desc'])) }}">Sale Date</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.customers.reviews', array_merge(request()->query(), ['sort' => 'customer_name', 'direction' => $sort === 'customer_name' ? $nextDirection : 'asc'])) }}">Customer</a>
                        </th>
                        <th>Phone</th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.customers.reviews', array_merge(request()->query(), ['sort' => 'product_name', 'direction' => $sort === 'product_name' ? $nextDirection : 'asc'])) }}">Product</a>
                        </th>
                        <th>Queue State</th>
                        <th>Suggested Rating</th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.customers.reviews', array_merge(request()->query(), ['sort' => 'line_total', 'direction' => $sort === 'line_total' ? $nextDirection : 'desc'])) }}">Line Total</a>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($reviews as $review)
                        <tr>
                            <td>{{ $review->sale_date }}</td>
                            <td>{{ $review->customer_name }}</td>
                            <td>{{ $review->customer_phone }}</td>
                            <td>{{ $review->product_name }}</td>
                            <td>{{ $review->review_state }}</td>
                            <td>{{ $review->suggested_rating }}/5</td>
                            <td>{{ number_format((float) $review->line_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="admin-table-empty">No review candidates found.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>

        <x-admin.table-pagination :paginator="$reviews" label="reviews" />
