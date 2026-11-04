@if (session('status'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('admin.ecommerce.catalog.partners.store') }}" enctype="multipart/form-data" class="admin-filter-panel p-6">
    @csrf
    <h3 class="text-lg font-semibold text-slate-900">Add partner</h3>
    <p class="mt-1 text-sm text-slate-600">Partners appear in the “Trusted presence” section on the storefront home page.</p>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
            <label for="partner_name" class="mb-1 block text-sm font-medium text-slate-700">Name</label>
            <input id="partner_name" name="name" type="text" value="{{ old('name') }}" required maxlength="120" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="partner_website_url" class="mb-1 block text-sm font-medium text-slate-700">Link (website, Google Maps, etc.)</label>
            <input id="partner_website_url" name="website_url" type="url" value="{{ old('website_url') }}" placeholder="https://..." maxlength="255" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
            @error('website_url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="partner_logo_file" class="mb-1 block text-sm font-medium text-slate-700">Logo (optional)</label>
            <input id="partner_logo_file" name="logo_file" type="file" accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
            <p class="mt-1 text-xs text-slate-500">Up to {{ $maxLogoMb }}MB. Without a logo, the partner name is shown as a text badge.</p>
            @error('logo_file')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="partner_sort_order" class="mb-1 block text-sm font-medium text-slate-700">Sort order</label>
            <input id="partner_sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
            @error('sort_order')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-slate-700 md:col-span-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
            Show on storefront home page
        </label>
    </div>
    <button type="submit" class="mt-5 rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Add partner</button>
</form>

<x-admin.ecommerce-data-table>
    <thead>
        <tr>
            <th>Logo</th>
            <th>Name</th>
            <th>Link</th>
            <th>Status</th>
            <th>Sort</th>
            <th class="admin-table-th-actions">Actions</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @forelse ($partners as $partner)
            <tr>
                <td>
                    @if ($partner->logo_path)
                        <img src="{{ asset('storage/' . $partner->logo_path) }}" alt="{{ $partner->name }}" class="h-12 w-20 rounded-md object-contain ring-1 ring-slate-200">
                    @else
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200">{{ $partner->name }}</span>
                    @endif
                </td>
                <td>{{ $partner->name }}</td>
                <td>
                    @if ($partner->website_url)
                        <a href="{{ $partner->website_url }}" target="_blank" rel="noopener noreferrer" class="text-sky-700 hover:text-sky-800">{{ \Illuminate\Support\Str::limit($partner->website_url, 40) }}</a>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </td>
                <td>
                    @if ($partner->is_active)
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">Active</span>
                    @else
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200">Hidden</span>
                    @endif
                </td>
                <td>{{ $partner->sort_order }}</td>
                <td class="admin-table-td-actions">
                    <x-admin.table-actions>
                        <details class="admin-table-inline-details">
                            <summary class="admin-table-action">Edit</summary>
                            <form method="POST" action="{{ route('admin.ecommerce.catalog.partners.update', $partner) }}" enctype="multipart/form-data" class="admin-table-inline-panel">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-700">Name</label>
                                    <input name="name" type="text" value="{{ old('name', $partner->name) }}" required maxlength="120" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-700">Link</label>
                                    <input name="website_url" type="url" value="{{ old('website_url', $partner->website_url) }}" placeholder="https://..." maxlength="255" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-700">Sort order</label>
                                    <input name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $partner->sort_order) }}" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-700">Replace logo (optional)</label>
                                    <input name="logo_file" type="file" accept=".jpg,.jpeg,.png,.webp,.svg,image/jpeg,image/png,image/webp,image/svg+xml" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                </div>
                                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $partner->is_active)) class="rounded border-slate-300 text-teal-600">
                                    Show on storefront home page
                                </label>
                                <x-admin.table-action type="submit" variant="primary">Save</x-admin.table-action>
                            </form>
                        </details>

                        <form method="POST" action="{{ route('admin.ecommerce.catalog.partners.destroy', $partner) }}" onsubmit="return confirm('Delete this partner?')">
                            @csrf
                            @method('DELETE')
                            <x-admin.table-action type="submit" variant="danger">Delete</x-admin.table-action>
                        </form>
                    </x-admin.table-actions>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="admin-table-empty">No partners added yet. Add one above to show it on the storefront home page.</td>
            </tr>
        @endforelse
    </tbody>
</x-admin.ecommerce-data-table>
