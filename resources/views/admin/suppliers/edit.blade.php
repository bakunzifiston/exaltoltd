<x-layouts.admin title="Edit Supplier">
    <section class="space-y-6">
        <h2 class="text-2xl font-semibold text-slate-900">Edit Supplier</h2>
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('admin.suppliers.update', $supplier) }}">
                @method('PUT')
                @include('admin.suppliers._form', ['submitLabel' => 'Save Changes'])
            </form>
        </div>
    </section>
</x-layouts.admin>
