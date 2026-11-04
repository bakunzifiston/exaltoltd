<form method="GET" class="admin-filter-panel">
            <input type="hidden" name="module" value="{{ $hubModule }}">
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-full max-w-sm">
                    <label for="search" class="mb-1 block text-sm font-medium text-slate-700">Search products</label>
                    <input
                        id="search"
                        name="search"
                        type="text"
                        value="{{ $search }}"
                        placeholder="Type, name, barcode..."
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
                        <th>Preview</th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.catalog.images', array_merge(request()->query(), ['sort' => 'type', 'direction' => $sort === 'type' ? $nextDirection : 'asc'])) }}">Name</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.catalog.images', array_merge(request()->query(), ['sort' => 'name', 'direction' => $sort === 'name' ? $nextDirection : 'asc'])) }}">Type</a>
                        </th>
                        <th>
                            <a class="admin-table-sort" href="{{ route('admin.ecommerce.catalog.images', array_merge(request()->query(), ['sort' => 'barcode', 'direction' => $sort === 'barcode' ? $nextDirection : 'asc'])) }}">Barcode</a>
                        </th>
                        <th>Image Status</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        <tr>
                            <td>
                                @if ($product->image_url)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->type }}" class="h-11 w-11 rounded-lg object-cover ring-1 ring-slate-200">
                                @else
                                    <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-500 ring-1 ring-slate-200">N/A</div>
                                @endif
                            </td>
                            <td>{{ $product->type }}</td>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->barcode }}</td>
                            <td>
                                @if ($product->has_image)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">Image Found</span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-200">Missing</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $product->image_source ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="admin-table-empty">No products found.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>

        <x-admin.table-pagination :paginator="$products" label="products" />
