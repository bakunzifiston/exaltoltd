<x-layouts.admin title="View Product">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Product Details</h2>
                <p class="mt-1 text-sm text-slate-500">View and manage this product record.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.products.edit', $product) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Edit</a>
                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Delete this product?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Delete</button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @if ($product->image_path)
                <div class="mb-6">
                    <p class="text-sm text-slate-500">Product Image</p>
                    <img src="{{ asset('storage/' . $product->image_path) }}" alt="{{ $product->name }}" class="mt-2 h-24 w-24 rounded-lg object-cover ring-1 ring-slate-200">
                </div>
            @endif

            <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div><dt class="text-sm text-slate-500">Name</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $product->type }}</dd></div>
                <div><dt class="text-sm text-slate-500">Type</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $product->name }}</dd></div>
                <div><dt class="text-sm text-slate-500">Barcode</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $product->barcode ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Description</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $product->description ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Created At</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $product->created_at?->format('Y-m-d H:i') }}</dd></div>
                <div><dt class="text-sm text-slate-500">Updated At</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $product->updated_at?->format('Y-m-d H:i') }}</dd></div>
            </dl>
        </div>
    </section>
</x-layouts.admin>
