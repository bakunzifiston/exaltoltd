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
                <div class="w-full max-w-[250px]">
                    <label for="event" class="mb-1 block text-sm font-medium text-slate-700">Event Filter</label>
                    <select
                        id="event"
                        name="event"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                        <option value="all" @selected($event === 'all')>All events</option>
                        <option value="delivery_pending" @selected($event === 'delivery_pending')>Delivery pending</option>
                        <option value="delivered" @selected($event === 'delivered')>Delivered</option>
                        <option value="payment_pending" @selected($event === 'payment_pending')>Payment pending/credit</option>
                        <option value="payment_received" @selected($event === 'payment_received')>Payment received</option>
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
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.customers.notifications', array_merge(request()->query(), ['sort' => 'sale_date', 'direction' => $sort === 'sale_date' ? $nextDirection : 'desc'])) }}">Sale Date</a>
                        </th>
                        <th>Sale ID</th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.customers.notifications', array_merge(request()->query(), ['sort' => 'customer_name', 'direction' => $sort === 'customer_name' ? $nextDirection : 'asc'])) }}">Customer</a>
                        </th>
                        <th>Contact</th>
                        <th>Event Type</th>
                        <th>Message Template</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($notifications as $notification)
                        <tr>
                            <td>{{ $notification->sale_date }}</td>
                            <td>{{ $notification->sales_id }}</td>
                            <td>{{ $notification->customer_name }}</td>
                            <td>{{ $notification->customer_Phone ?: '-' }}</td>
                            <td>{{ $notification->event_type }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ $notification->event_message }}</td>
                            <td>{{ number_format((float) $notification->total_revenue, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="admin-table-empty">No notification events found.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>

        <x-admin.table-pagination :paginator="$notifications" label="notifications" />
