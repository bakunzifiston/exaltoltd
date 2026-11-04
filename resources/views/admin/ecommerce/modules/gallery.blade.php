@if (session('status'))
    <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('admin.ecommerce.catalog.gallery.store') }}" enctype="multipart/form-data" class="admin-filter-panel p-6">
    @csrf
    <h3 class="text-lg font-semibold text-slate-900">Upload gallery photos</h3>
    <p class="mt-1 text-sm text-slate-600">Photos are grouped into albums on the storefront gallery grid (home page and /gallery).</p>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        <div>
            <label for="gallery_album_id" class="mb-1 block text-sm font-medium text-slate-700">Album</label>
            <select id="gallery_album_id" name="album_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
                <option value="">Create new album</option>
                @foreach ($albums as $album)
                    <option value="{{ $album->id }}" @selected((string) old('album_id') === (string) $album->id)>{{ $album->title }} ({{ $album->photos_count }} photos)</option>
                @endforeach
            </select>
            @error('album_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="gallery_album_title" class="mb-1 block text-sm font-medium text-slate-700">New album title</label>
            <input id="gallery_album_title" name="album_title" type="text" value="{{ old('album_title') }}" placeholder="e.g. Production, Events" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
            <p class="mt-1 text-xs text-slate-500">Used when “Create new album” is selected.</p>
            @error('album_title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="gallery_title" class="mb-1 block text-sm font-medium text-slate-700">Photo caption (optional)</label>
            <input id="gallery_title" name="title" type="text" value="{{ old('title') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
            <p class="mt-1 text-xs text-slate-500">If multiple images are selected, this text is used as a prefix.</p>
            @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="gallery_sort_order" class="mb-1 block text-sm font-medium text-slate-700">Sort order</label>
            <input id="gallery_sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
            @error('sort_order')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="md:col-span-2">
            <label for="gallery_image_files" class="mb-1 block text-sm font-medium text-slate-700">Image files (jpg, jpeg, png, webp)</label>
            <input id="gallery_image_files" name="image_files[]" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
            <p class="mt-1 text-xs text-slate-500">Maximum file size per image: {{ $maxImageMb }}MB.</p>
            @error('image_files')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('image_files.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <label class="inline-flex items-center gap-2 text-sm text-slate-700 md:col-span-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
            Show on storefront home page gallery
        </label>
    </div>
    <button type="submit" class="mt-5 rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Upload photos</button>
</form>

<x-admin.ecommerce-data-table>
    <thead>
        <tr>
            <th>Preview</th>
            <th>Album</th>
            <th>Caption</th>
            <th>Status</th>
            <th>Sort</th>
            <th class="admin-table-th-actions">Actions</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @forelse ($photos as $photo)
            <tr>
                <td>
                    <img src="{{ $photo->image_url }}" alt="{{ $photo->alt }}" class="h-16 w-28 rounded-md object-cover ring-1 ring-slate-200">
                </td>
                <td>{{ $photo->album?->title ?? '—' }}</td>
                <td>{{ $photo->title }}</td>
                <td>
                    @if ($photo->is_active)
                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">Active</span>
                    @else
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200">Hidden</span>
                    @endif
                </td>
                <td>{{ $photo->sort_order }}</td>
                <td class="admin-table-td-actions">
                    <x-admin.table-actions>
                        <details class="admin-table-inline-details">
                            <summary class="admin-table-action">Edit</summary>
                            <form method="POST" action="{{ route('admin.ecommerce.catalog.gallery.update', $photo) }}" enctype="multipart/form-data" class="admin-table-inline-panel">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-700">Album</label>
                                    <select name="album_id" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                        @foreach ($albums as $albumOption)
                                            <option value="{{ $albumOption->id }}" @selected((int) old('album_id', $photo->album_id) === $albumOption->id)>{{ $albumOption->title }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-700">Caption</label>
                                    <input name="title" type="text" value="{{ old('title', $photo->title) }}" required maxlength="120" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-700">Sort order</label>
                                    <input name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $photo->sort_order) }}" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-700">Replace image (optional)</label>
                                    <input name="image_file" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                </div>
                                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $photo->is_active)) class="rounded border-slate-300 text-teal-600">
                                    Show on storefront home page gallery
                                </label>
                                <x-admin.table-action type="submit" variant="primary">Save</x-admin.table-action>
                            </form>
                        </details>

                        <form method="POST" action="{{ route('admin.ecommerce.catalog.gallery.destroy', $photo) }}" onsubmit="return confirm('Delete this gallery photo?')">
                            @csrf
                            @method('DELETE')
                            <x-admin.table-action type="submit" variant="danger">Delete</x-admin.table-action>
                        </form>
                    </x-admin.table-actions>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="admin-table-empty">No gallery photos uploaded yet. Upload images above to populate the storefront gallery.</td>
            </tr>
        @endforelse
    </tbody>
</x-admin.ecommerce-data-table>
