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
                        placeholder="Product name, type, barcode..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                </div>
                <div class="w-full max-w-[240px]">
                    <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Sync Status</label>
                    <select
                        id="status"
                        name="status"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                        <option value="all" @selected($status === 'all')>All</option>
                        <option value="balanced" @selected($status === 'balanced')>Balanced</option>
                        <option value="inventory_ahead" @selected($status === 'inventory_ahead')>Inventory ahead</option>
                        <option value="production_ahead" @selected($status === 'production_ahead')>Production ahead</option>
                        <option value="out_of_stock" @selected($status === 'out_of_stock')>Out of stock</option>
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
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.fulfillment.inventory-sync', array_merge(request()->query(), ['sort' => 'product_name', 'direction' => $sort === 'product_name' ? $nextDirection : 'asc'])) }}">Product</a>
                        </th>
                        <th>Barcode</th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.fulfillment.inventory-sync', array_merge(request()->query(), ['sort' => 'stock_on_hand', 'direction' => $sort === 'stock_on_hand' ? $nextDirection : 'desc'])) }}">Inventory Qty</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.fulfillment.inventory-sync', array_merge(request()->query(), ['sort' => 'finished_goods', 'direction' => $sort === 'finished_goods' ? $nextDirection : 'desc'])) }}">Finished Goods</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.fulfillment.inventory-sync', array_merge(request()->query(), ['sort' => 'sold_units', 'direction' => $sort === 'sold_units' ? $nextDirection : 'desc'])) }}">Sold Units</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.fulfillment.inventory-sync', array_merge(request()->query(), ['sort' => 'sellable_qty', 'direction' => $sort === 'sellable_qty' ? $nextDirection : 'desc'])) }}">Sellable Qty</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.fulfillment.inventory-sync', array_merge(request()->query(), ['sort' => 'sync_gap', 'direction' => $sort === 'sync_gap' ? $nextDirection : 'desc'])) }}">Sync Gap</a>
                        </th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($syncRows as $row)
                        <tr>
                            <td>{{ $row->product_name }}</td>
                            <td>{{ $row->barcode ?: '-' }}</td>
                            <td>{{ number_format((float) $row->stock_on_hand, 2) }}</td>
                            <td>{{ number_format((float) $row->finished_goods, 2) }}</td>
                            <td>{{ number_format((float) $row->sold_units, 2) }}</td>
                            <td>{{ number_format((float) $row->sellable_qty, 2) }}</td>
                            <td>{{ number_format((float) $row->sync_gap, 2) }}</td>
                            <td>{{ $row->sync_status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="admin-table-empty">No synchronization records found.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>

        <x-admin.table-pagination :paginator="$syncRows" label="items" />
