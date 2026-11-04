<x-layouts.admin title="Create Sale">
    <section class="space-y-6">
        <div><h2 class="text-2xl font-semibold text-slate-900">Create Sale</h2></div>
        <form method="POST" action="{{ route('admin.sales.store') }}" enctype="multipart/form-data" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @include('admin.sales._form', ['submitLabel' => 'Create Sale'])
        </form>
    </section>
</x-layouts.admin>
