<x-layouts.admin title="Edit Product">
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Edit Product</h2>
            <p class="mt-1 text-sm text-slate-500">Update this product record.</p>
        </div>

        <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @method('PUT')
            @include('admin.products._form', ['submitLabel' => 'Save Changes'])
        </form>
    </section>
</x-layouts.admin>
