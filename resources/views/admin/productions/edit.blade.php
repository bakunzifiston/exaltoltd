<x-layouts.admin title="Edit Production">
    <section class="space-y-6">
        <div><h2 class="text-2xl font-semibold text-slate-900">Edit Production</h2></div>
        <form method="POST" action="{{ route('admin.productions.update', $production) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @method('PUT')
            @include('admin.productions._form', ['submitLabel' => 'Save Changes'])
        </form>
    </section>
</x-layouts.admin>
