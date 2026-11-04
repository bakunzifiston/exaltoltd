<x-layouts.admin title="Record Expense">
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Expenses</h2>
            <p class="mt-1 text-sm text-slate-500">Add a COGS or operating expense entry.</p>
        </div>

        @include('admin.expenses._module-nav', ['active' => 'add', 'year' => request('year', now()->year)])

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.expenses.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @include('admin.expenses._form', ['submitLabel' => 'Save expense', 'formYear' => request('year', now()->year)])
        </form>
    </section>
</x-layouts.admin>
