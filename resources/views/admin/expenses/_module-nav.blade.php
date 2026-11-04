@props([
    'active' => 'overview',
    'year' => null,
    'cogsMonth' => null,
    'operatingMonth' => null,
])

@php
    $year = (int) ($year ?? request('year', now()->year));
    $cogsMonth = (int) ($cogsMonth ?? request('cogs_month', now()->month));
    $operatingMonth = (int) ($operatingMonth ?? request('operating_month', now()->month));

    $indexQuery = [
        'year' => $year,
        'cogs_month' => $cogsMonth,
        'operating_month' => $operatingMonth,
    ];

    $tabClass = fn (string $key): string => $active === $key
        ? 'border-teal-700 text-teal-800'
        : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700';

    $items = [
        'overview' => 'Overview',
        'cogs' => 'Cost of goods sold',
        'operating' => 'Operating expenses',
        'add' => 'Add expense',
        'all' => 'All expenses',
        'categories' => 'Categories',
    ];
@endphp

<nav class="-mb-px flex flex-wrap gap-1 border-b border-slate-200" aria-label="Expenses module">
    @foreach ($items as $key => $label)
        @if ($key === 'categories')
            <a
                href="{{ route('admin.expense-categories.index', $indexQuery) }}"
                class="border-b-2 px-4 py-3 text-sm font-medium transition {{ $tabClass('categories') }}"
            >
                {{ $label }}
            </a>
        @else
            <a
                href="{{ route('admin.expenses.index', array_merge($indexQuery, ['tab' => $key])) }}"
                class="border-b-2 px-4 py-3 text-sm font-medium transition {{ $tabClass($key) }}"
            >
                {{ $label }}
            </a>
        @endif
    @endforeach
</nav>
