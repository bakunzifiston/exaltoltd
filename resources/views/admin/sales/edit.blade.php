<x-layouts.admin title="Edit Sale">
    <section class="space-y-6">
        <div><h2 class="text-2xl font-semibold text-slate-900">Edit Sale</h2></div>
        <form method="POST" action="{{ route('admin.sales.update', $sale) }}" enctype="multipart/form-data" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @method('PUT')
            @include('admin.sales._form', ['submitLabel' => 'Save Changes'])
        </form>
    </section>
</x-layouts.admin>
