<x-layouts.admin title="Edit Customer">
    <section class="space-y-6">
        <h2 class="text-2xl font-semibold text-slate-900">Edit Customer</h2>
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('admin.customers.update', $customer) }}">
                @method('PUT')
                @include('admin.customers._form', ['submitLabel' => 'Save Changes'])
            </form>
        </div>
    </section>
</x-layouts.admin>
