<x-layouts.admin title="Create Inventory Record">
    <section class="space-y-6">
        <div><h2 class="text-2xl font-semibold text-slate-900">Create Inventory Record</h2></div>
        <form method="POST" action="{{ route('admin.inventory-records.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @include('admin.inventory-records._form', ['submitLabel' => 'Create Record'])
        </form>
    </section>
</x-layouts.admin>
