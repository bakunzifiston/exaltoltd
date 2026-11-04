<x-layouts.admin title="Expense Categories">
    <section
        x-data="{
            showAdd: @js($errors->any() && old('name')),
            editingId: null,
            deleteId: null,
            deleteName: '',
            deleteCount: 0,
            startEdit(id) {
                this.editingId = id;
                this.showAdd = false;
            },
            cancelEdit() {
                this.editingId = null;
            },
            confirmDelete(id, name, count) {
                this.deleteId = id;
                this.deleteName = name;
                this.deleteCount = count;
            },
            cancelDelete() {
                this.deleteId = null;
                this.deleteName = '';
                this.deleteCount = 0;
            },
        }"
        class="space-y-6"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Expenses</h2>
                <p class="mt-1 text-sm text-slate-500">Manage COGS and operating expense categories.</p>
            </div>
            <button
                type="button"
                @click="showAdd = !showAdd; editingId = null"
                class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800"
            >
                Add operating category
            </button>
        </div>

        @include('admin.expenses._module-nav', ['active' => 'categories'])

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        @if (session('warning'))
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ session('warning') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div x-show="showAdd" x-cloak class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">New operating category</h3>
            <p class="mt-1 text-sm text-slate-500">COGS categories are managed through Inventory — only operating categories can be added here.</p>
            <form method="POST" action="{{ route('admin.expense-categories.store') }}" class="mt-4 flex flex-wrap items-end gap-4">
                @csrf
                <input type="hidden" name="type" value="operating">
                <div class="min-w-[14rem] flex-1">
                    <label for="new-name" class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                    <input
                        id="new-name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        required
                        maxlength="255"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none"
                        placeholder="e.g. Office supplies"
                    >
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">Save</button>
                    <button type="button" @click="showAdd = false" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                </div>
            </form>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            @include('admin.expenses.categories._table', [
                'categories' => $cogsCategories,
                'typeLabel' => 'COGS',
                'badgeType' => 'cogs',
            ])
            @include('admin.expenses.categories._table', [
                'categories' => $operatingCategories,
                'typeLabel' => 'Operating',
                'badgeType' => 'operating',
            ])
        </div>

        {{-- Delete confirmation --}}
        <div
            x-show="deleteId !== null"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
            role="dialog"
            aria-modal="true"
        >
            <div @click.outside="cancelDelete()" class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900">Delete category?</h3>
                <p class="mt-2 text-sm text-slate-600">
                    You are about to delete <span class="font-medium text-slate-900" x-text="deleteName"></span>.
                </p>
                <p x-show="deleteCount > 0" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                    This category has <span x-text="deleteCount"></span> linked expense(s) and cannot be deleted. Reassign or delete those expenses first.
                </p>
                <p x-show="deleteCount === 0" class="mt-3 text-sm text-slate-500">This category has no linked expenses.</p>
                <div class="mt-6 flex flex-wrap justify-end gap-2">
                    <button type="button" @click="cancelDelete()" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
                    <template x-if="deleteCount === 0">
                        <form x-bind:action="`{{ url('admin-app/expenses/categories') }}/${deleteId}`" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700">Delete</button>
                        </form>
                    </template>
                </div>
            </div>
        </div>
    </section>

    <style>[x-cloak] { display: none !important; }</style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
</x-layouts.admin>
