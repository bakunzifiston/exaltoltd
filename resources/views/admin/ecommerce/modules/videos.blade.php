@if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.ecommerce.catalog.videos.store') }}" enctype="multipart/form-data" class="admin-filter-panel p-6">
            @csrf
            <h3 class="text-lg font-semibold text-slate-900">Upload Video</h3>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <label for="title" class="mb-1 block text-sm font-medium text-slate-700">Title (optional)</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-500">If multiple videos are selected, this text is used as a prefix.</p>
                    @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="sort_order" class="mb-1 block text-sm font-medium text-slate-700">Sort Order</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', 0) }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
                    @error('sort_order')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                    <label for="video_file" class="mb-1 block text-sm font-medium text-slate-700">Video File (mp4, webm, ogg, mov)</label>
                    <input id="video_file" name="video_files[]" type="file" accept=".mp4,.webm,.ogg,.mov,.qt,video/mp4,video/webm,video/ogg,video/quicktime" multiple required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-500">Maximum file size per video: {{ $maxVideoMb }}MB.</p>
                    @error('video_files')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    @error('video_files.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    Show on storefront home page
                </label>
            </div>
            <button type="submit" class="mt-5 rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Upload Video</button>
        </form>
        <x-admin.ecommerce-data-table>
                <thead>
                    <tr>
                        <th>Preview</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Sort</th>
                        <th class="admin-table-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($videos as $video)
                        <tr>
                            <td>
                                <video class="h-16 w-28 rounded-md object-cover ring-1 ring-slate-200" muted playsinline preload="metadata">
                                    <source src="{{ asset('storage/' . $video->video_path) }}">
                                </video>
                            </td>
                            <td>{{ $video->title }}</td>
                            <td>
                                @if ($video->is_active)
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">Active</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 ring-1 ring-slate-200">Hidden</span>
                                @endif
                            </td>
                            <td>{{ $video->sort_order }}</td>
                            <td class="admin-table-td-actions">
                                <x-admin.table-actions>
                                    <details class="admin-table-inline-details">
                                        <summary class="admin-table-action">Edit</summary>
                                        <form method="POST" action="{{ route('admin.ecommerce.catalog.videos.update', $video) }}" enctype="multipart/form-data" class="admin-table-inline-panel">
                                            @csrf
                                            @method('PUT')
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-slate-700">Title</label>
                                                <input name="title" type="text" value="{{ old('title', $video->title) }}" required maxlength="120" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-slate-700">Sort Order</label>
                                                <input name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $video->sort_order) }}" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-medium text-slate-700">Replace Video (optional)</label>
                                                <input name="video_file" type="file" accept=".mp4,.webm,.ogg,.mov,.qt,video/mp4,video/webm,video/ogg,video/quicktime" class="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-xs">
                                            </div>
                                            <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                                                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $video->is_active)) class="rounded border-slate-300 text-teal-600">
                                                Show on storefront home page
                                            </label>
                                            <x-admin.table-action type="submit" variant="primary">Save</x-admin.table-action>
                                        </form>
                                    </details>

                                    <form method="POST" action="{{ route('admin.ecommerce.catalog.videos.destroy', $video) }}" onsubmit="return confirm('Delete this video?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-admin.table-action type="submit" variant="danger">Delete</x-admin.table-action>
                                    </form>
                                </x-admin.table-actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="admin-table-empty">No videos uploaded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
        </x-admin.ecommerce-data-table>
