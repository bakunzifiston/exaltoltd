<form method="GET" class="admin-filter-panel">
            <input type="hidden" name="module" value="{{ $hubModule }}">
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-full max-w-sm">
                    <label for="search" class="mb-1 block text-sm font-medium text-slate-700">Search category</label>
                    <input
                        id="search"
                        name="search"
                        type="text"
                        value="{{ $search }}"
                        placeholder="Category name..."
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
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.catalog.categories', array_merge(request()->query(), ['sort' => 'category', 'direction' => $sort === 'category' ? $nextDirection : 'asc'])) }}">Category</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.catalog.categories', array_merge(request()->query(), ['sort' => 'products_count', 'direction' => $sort === 'products_count' ? $nextDirection : 'desc'])) }}">Products</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.catalog.categories', array_merge(request()->query(), ['sort' => 'stock_on_hand', 'direction' => $sort === 'stock_on_hand' ? $nextDirection : 'desc'])) }}">Stock On Hand</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.catalog.categories', array_merge(request()->query(), ['sort' => 'sold_units', 'direction' => $sort === 'sold_units' ? $nextDirection : 'desc'])) }}">Sold Units</a>
                        </th>
                        <th class="admin-table-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->category }}</td>
                            <td>{{ number_format((float) $category->products_count, 0) }}</td>
                            <td>{{ number_format((float) $category->stock_on_hand, 2) }}</td>
                            <td>{{ number_format((float) $category->sold_units, 2) }}</td>
                            <td class="admin-table-td-actions">
                                <x-admin.table-actions>
                                    <x-admin.table-action :href="route('admin.ecommerce.catalog.products', ['category' => $category->category === 'Uncategorized' ? '' : $category->category])">
                                        Browse
                                    </x-admin.table-action>
                                </x-admin.table-actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="admin-table-empty">No categories found.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>

        <x-admin.table-pagination :paginator="$categories" label="categories" />
