<x-layouts.admin title="New Customer">
    <section class="space-y-6">
        <h2 class="text-2xl font-semibold text-slate-900">New Customer</h2>
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('admin.customers.store') }}">
                @include('admin.customers._form', ['submitLabel' => 'Create Customer'])
            </form>
        </div>
    </section>
</x-layouts.admin>
