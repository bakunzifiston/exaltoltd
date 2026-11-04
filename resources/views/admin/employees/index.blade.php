<x-layouts.admin title="Employees">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Employees</h2>
                <p class="mt-1 text-sm text-slate-500">Manage employee records and profile photos.</p>
            </div>
            <a href="{{ route('admin.employees.create') }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white hover:bg-teal-800">
                New Employee
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        <form method="GET" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-full max-w-sm">
                    <label for="search" class="mb-1 block text-sm font-medium text-slate-700">Search</label>
                    <input id="search" name="search" type="text" value="{{ $search }}" placeholder="ID, name, phone, email..."
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
                </div>
                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Apply</button>
            </div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    @php($nextDirection = $direction === 'asc' ? 'desc' : 'asc')
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Photo</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <a href="{{ route('admin.employees.index', array_merge(request()->query(), ['sort' => 'id', 'direction' => $sort === 'id' ? $nextDirection : 'asc'])) }}">Employee ID</a>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">
                            <a href="{{ route('admin.employees.index', array_merge(request()->query(), ['sort' => 'name', 'direction' => $sort === 'name' ? $nextDirection : 'asc'])) }}">Name</a>
                        </th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Position</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Phone</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Email</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Province</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">District</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($employees as $employee)
                        <tr>
                            <td class="px-4 py-3">
                                @if ($employee->photo)
                                    <img src="{{ Storage::url($employee->photo) }}" alt="{{ $employee->name }}" class="h-10 w-10 rounded-full object-cover">
                                @else
                                    <img src="https://ui-avatars.com/api/?name={{ urlencode($employee->name ?: 'E') }}&color=7F9CF5&background=EBF4FF" alt="{{ $employee->name }}" class="h-10 w-10 rounded-full object-cover">
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-800">{{ $employee->id }}</td>
                            <td class="px-4 py-3 text-slate-800">{{ $employee->name }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $employee->position }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $employee->phone }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $employee->email }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $employee->province }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ $employee->district }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.employees.show', $employee) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                                    <a href="{{ route('admin.employees.edit', $employee) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                                    <form method="POST" action="{{ route('admin.employees.destroy', $employee) }}" onsubmit="return confirm('Delete this employee?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-slate-500">No employees found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.table-pagination :paginator="$employees" label="employees" />
    </section>
</x-layouts.admin>
