<x-layouts.admin title="Create Production">
    <section class="space-y-6">
        <div><h2 class="text-2xl font-semibold text-slate-900">Create Production</h2></div>
        <form method="POST" action="{{ route('admin.productions.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @include('admin.productions._form', ['submitLabel' => 'Create Production'])
        </form>
    </section>
</x-layouts.admin>
