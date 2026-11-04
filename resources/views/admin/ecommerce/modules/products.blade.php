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
                        placeholder="Name, type, barcode, description..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                </div>
                <input type="hidden" name="category" value="{{ $category }}">

                <div class="w-full max-w-[220px]">
                    <label for="stock" class="mb-1 block text-sm font-medium text-slate-700">Stock Filter</label>
                    <select
                        id="stock"
                        name="stock"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                    >
                        <option value="all" @selected($stockFilter === 'all')>All</option>
                        <option value="in_stock" @selected($stockFilter === 'in_stock')>In stock</option>
                        <option value="out_of_stock" @selected($stockFilter === 'out_of_stock')>Out of stock</option>
                    </select>
                </div>

                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    Apply
                </button>
                @if ($category !== '')
                    <a href="{{ route('admin.ecommerce.catalog.products', request()->except('category')) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        Clear Category
                    </a>
                @endif
            </div>
        </form>
        <x-admin.ecommerce-data-table data-bulk="{{ route('admin.bulk-delete', 'products') }}">
                <thead>
                    @php($nextDirection = $direction === 'asc' ? 'desc' : 'asc')
                    <tr>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.catalog.products', array_merge(request()->query(), ['sort' => 'type', 'direction' => $sort === 'type' ? $nextDirection : 'asc'])) }}">Name</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.catalog.products', array_merge(request()->query(), ['sort' => 'name', 'direction' => $sort === 'name' ? $nextDirection : 'asc'])) }}">Type</a>
                        </th>
                        <th>Barcode</th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.catalog.products', array_merge(request()->query(), ['sort' => 'stock_on_hand', 'direction' => $sort === 'stock_on_hand' ? $nextDirection : 'desc'])) }}">Stock On Hand</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.catalog.products', array_merge(request()->query(), ['sort' => 'available_finished_goods', 'direction' => $sort === 'available_finished_goods' ? $nextDirection : 'desc'])) }}">Finished Goods</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.catalog.products', array_merge(request()->query(), ['sort' => 'sold_units', 'direction' => $sort === 'sold_units' ? $nextDirection : 'desc'])) }}">Sold Units</a>
                        </th>
                        <th>Min Qty</th>
                        <th class="admin-table-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        <tr>
                            <td>{{ $product->type }}</td>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->barcode ?: 'N/A' }}</td>
                            <td>{{ number_format((float) $product->stock_on_hand, 2) }}</td>
                            <td>{{ number_format((float) $product->available_finished_goods, 2) }}</td>
                            <td>{{ number_format((float) $product->sold_units, 2) }}</td>
                            <td>{{ $product->min_order_qty ?? 5 }}</td>
                            <td class="admin-table-td-actions !w-auto min-w-[13rem]">
                                <x-admin.table-actions>
                                    <x-admin.table-action :href="route('admin.products.show', $product)">View</x-admin.table-action>
                                    <x-admin.table-action :href="route('admin.products.edit', $product)">Edit</x-admin.table-action>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.products.destroy', $product) }}"
                                        class="inline-flex items-center"
                                        onsubmit="return confirm('Delete this product?')"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="from_ecommerce_hub" value="1">
                                        <x-admin.table-action type="submit" variant="danger">Delete</x-admin.table-action>
                                    </form>
                                </x-admin.table-actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="admin-table-empty">No products found.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>

        <x-admin.table-pagination :paginator="$products" label="products" />
