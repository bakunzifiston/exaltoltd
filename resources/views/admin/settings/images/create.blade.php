<x-layouts.admin title="Upload Site Image">
    <section class="max-w-lg space-y-6">
        <div>
            <a href="{{ route('admin.settings.images.index') }}" class="text-sm text-teal-700 hover:underline">← Back to images</a>
            <h2 class="mt-2 text-2xl font-semibold text-slate-900">Upload Image</h2>
        </div>

        <form method="POST" action="{{ route('admin.settings.images.store') }}" enctype="multipart/form-data"
            class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Context <span class="text-rose-500">*</span></label>
                <select name="context" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20" required>
                    <option value="">Select section…</option>
                    @foreach($contexts as $ctx)
                        <option value="{{ $ctx }}" @selected(old('context') === $ctx)>{{ ucfirst($ctx) }}</option>
                    @endforeach
                </select>
                @error('context')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Label <span class="text-rose-500">*</span></label>
                <input name="label" type="text" value="{{ old('label') }}"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20"
                    placeholder="e.g. Team Photo 2025" maxlength="120" required>
                @error('label')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Image File <span class="text-rose-500">*</span></label>
                <input name="image" type="file" accept="image/*"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 file:mr-3 file:rounded file:border-0 file:bg-teal-700 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white hover:file:bg-teal-800"
                    required>
                <p class="mt-1 text-xs text-slate-400">Max 4 MB. JPEG, PNG, WebP, GIF.</p>
                @error('image')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Sort Order</label>
                    <input name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', 0) }}"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20">
                </div>
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))
                            class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                        <span class="text-sm text-slate-700">Active</span>
                    </label>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <a href="{{ route('admin.settings.images.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="rounded-lg bg-teal-700 px-5 py-2 text-sm font-semibold text-white hover:bg-teal-800">Upload</button>
            </div>
        </form>
    </section>
</x-layouts.admin>
