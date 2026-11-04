@props([
    'categories',
    'typeLabel',
    'badgeType',
])

<div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
        <h3 class="text-base font-semibold text-slate-900">{{ $typeLabel }} categories</h3>
        <x-admin.expense-type-badge :type="$badgeType" />
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="w-12 px-4 py-3 text-left font-semibold text-slate-600">#</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Category name</th>
                    <th class="px-4 py-3 text-right font-semibold text-slate-600">Expenses logged</th>
                    <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $index => $category)
                    <tr x-show="editingId !== {{ $category->id }}">
                        <td class="px-4 py-3 text-slate-500">{{ $index + 1 }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $category->name }}</td>
                        <td class="px-4 py-3 text-right text-slate-700">{{ number_format($category->expenses_count) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    @click="startEdit({{ $category->id }})"
                                    class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    Edit
                                </button>
                                <button
                                    type="button"
                                    @click="confirmDelete({{ $category->id }}, @js($category->name), {{ $category->expenses_count }})"
                                    class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100"
                                >
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr x-show="editingId === {{ $category->id }}" x-cloak>
                        <td class="px-4 py-3 text-slate-500">{{ $index + 1 }}</td>
                        <td colspan="3" class="px-4 py-3">
                            <form method="POST" action="{{ route('admin.expense-categories.update', $category) }}" class="flex flex-wrap items-end gap-3">
                                @csrf
                                @method('PUT')
                                <div class="min-w-[12rem] flex-1">
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Name</label>
                                    <input
                                        type="text"
                                        name="name"
                                        value="{{ old('name', $category->name) }}"
                                        required
                                        maxlength="255"
                                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                                    >
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Type</label>
                                    @if ($badgeType === 'cogs')
                                        <input type="hidden" name="type" value="cogs">
                                        <span class="inline-block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">COGS (read-only)</span>
                                    @else
                                        <input type="hidden" name="type" value="operating">
                                        <span class="inline-block rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500">Operating (read-only)</span>
                                    @endif
                                </div>
                                <div class="flex gap-2">
                                    <button type="submit" class="rounded-lg bg-teal-700 px-3 py-2 text-xs font-medium text-white hover:bg-teal-800">Save</button>
                                    <button type="button" @click="cancelEdit()" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-8 text-center text-slate-500">No categories yet — add one above.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
