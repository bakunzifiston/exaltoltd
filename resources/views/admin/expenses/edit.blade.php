<x-layouts.admin title="Edit Expense">
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Expenses</h2>
            <p class="mt-1 text-sm text-slate-500">Update {{ $expense->name }} ({{ $expense->expense_date->format('j M Y') }}).</p>
        </div>

        @include('admin.expenses._module-nav', [
            'active' => 'add',
            'year' => $expense->year,
            'cogsMonth' => $expense->month,
            'operatingMonth' => $expense->month,
        ])

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.expenses.update', $expense) }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @include('admin.expenses._form', ['submitLabel' => 'Update expense'])
        </form>
    </section>
</x-layouts.admin>
