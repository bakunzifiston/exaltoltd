<x-layouts.admin title="View Employee">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Employee Details</h2>
                <p class="mt-1 text-sm text-slate-500">View and manage this employee record.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.employees.edit', $employee) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Edit</a>
                <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}" onsubmit="return confirm('Delete this employee?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Delete</button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-6">
                @if ($employee->photo)
                    <img src="{{ Storage::url($employee->photo) }}" alt="{{ $employee->name }}" class="h-24 w-24 rounded-full object-cover">
                @else
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($employee->name ?: 'E') }}&color=7F9CF5&background=EBF4FF" alt="{{ $employee->name }}" class="h-24 w-24 rounded-full object-cover">
                @endif
            </div>

            <dl class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div><dt class="text-sm text-slate-500">Employee ID</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $employee->id }}</dd></div>
                <div><dt class="text-sm text-slate-500">Name</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $employee->name }}</dd></div>
                <div><dt class="text-sm text-slate-500">Position</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $employee->position ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Phone</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $employee->phone ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Email</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $employee->email ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Province</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $employee->province ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-500">District</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $employee->district ?? '—' }}</dd></div>
                <div><dt class="text-sm text-slate-500">Created At</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $employee->created_at?->format('Y-m-d H:i') }}</dd></div>
            </dl>
        </div>
    </section>
</x-layouts.admin>
