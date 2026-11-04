<x-layouts.admin title="Create User">
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Create User</h2>
            <p class="mt-1 text-sm text-slate-500">Add a new platform user.</p>
        </div>

        <form method="POST" action="{{ route('admin.users.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @include('admin.users._form', ['submitLabel' => 'Create User'])
        </form>
    </section>
</x-layouts.admin>
