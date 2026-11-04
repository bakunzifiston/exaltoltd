<x-layouts.admin title="Create Product">
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Create Product</h2>
            <p class="mt-1 text-sm text-slate-500">Add a new product record.</p>
        </div>

        <form method="POST" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @include('admin.products._form', ['submitLabel' => 'Create Product'])
        </form>
    </section>
</x-layouts.admin>
