<x-layouts.admin title="Create Employee">
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Create Employee</h2>
            <p class="mt-1 text-sm text-slate-500">Add a new employee profile.</p>
        </div>

        <form method="POST" action="{{ route('admin.employees.store') }}" enctype="multipart/form-data" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @include('admin.employees._form', ['submitLabel' => 'Create Employee'])
        </form>
    </section>
</x-layouts.admin>
