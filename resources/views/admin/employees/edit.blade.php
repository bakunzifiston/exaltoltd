<x-layouts.admin title="Edit Employee">
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Edit Employee</h2>
            <p class="mt-1 text-sm text-slate-500">Update employee details and profile photo.</p>
        </div>

        <form method="POST" action="{{ route('admin.employees.update', $employee) }}" enctype="multipart/form-data" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @method('PUT')
            @include('admin.employees._form', ['submitLabel' => 'Save Changes'])
        </form>
    </section>
</x-layouts.admin>
