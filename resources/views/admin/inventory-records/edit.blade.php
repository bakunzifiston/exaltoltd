<x-layouts.admin title="Edit Inventory Record">
    <section class="space-y-6">
        <div><h2 class="text-2xl font-semibold text-slate-900">Edit Inventory Record</h2></div>
        <form method="POST" action="{{ route('admin.inventory-records.update', $inventoryRecord) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @method('PUT')
            @include('admin.inventory-records._form', ['submitLabel' => 'Save Changes'])
        </form>
    </section>
</x-layouts.admin>
