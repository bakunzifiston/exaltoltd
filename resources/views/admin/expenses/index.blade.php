@php
    $formatRwf = fn (float $amount): string => number_format($amount, 0, '.', ',') . ' RWF';
    $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    $peakKpi = $yearSummary['peak_month_label']
        ? $yearSummary['peak_month_label'] . ' · ' . number_format($yearSummary['peak_month_total'], 0, '.', ',') . ' RWF'
        : '—';
@endphp

<x-layouts.admin title="Expenses">
    <section class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Expenses</h2>
                <p class="mt-1 text-sm text-slate-500">COGS and operating expense tracking for {{ $year }}.</p>
            </div>
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="hidden" name="cogs_month" value="{{ $cogsMonth }}">
                <input type="hidden" name="operating_month" value="{{ $operatingMonth }}">
                <label for="filter-year" class="text-sm font-medium text-slate-700">Year</label>
                <select id="filter-year" name="year" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
                    @for ($y = now()->year + 1; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                    @endfor
                </select>
            </form>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
        @endif

        @if ($errors->any() && $tab === 'add')
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('admin.expenses._module-nav', [
            'active' => $tab,
            'year' => $year,
            'cogsMonth' => $cogsMonth,
            'operatingMonth' => $operatingMonth,
        ])

        @if ($tab === 'overview')
        <div class="space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-admin.kpi-card label="Total expenses" :value="$formatRwf($yearSummary['grand_total'])" icon="banknotes" tone="teal" />
                <x-admin.kpi-card label="Total COGS" :value="$formatRwf($yearSummary['total_cogs'])" icon="box" tone="gold" />
                <x-admin.kpi-card label="Total operating expenses" :value="$formatRwf($yearSummary['total_operating'])" icon="chart" tone="violet" />
                <x-admin.kpi-card label="Peak expense month" :value="$peakKpi" icon="clock" tone="slate" />
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Monthly comparison</h3>
                        <p class="text-sm text-slate-500">COGS vs operating by month ({{ $year }}).</p>
                    </div>
                    <div class="flex flex-wrap gap-4 text-xs font-medium text-slate-600">
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background-color:#0F6E56"></span> COGS</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background-color:#534AB7"></span> Operating</span>
                    </div>
                </div>
                <div class="mt-6 h-80">
                    <canvas id="expensesMonthlyChart" aria-label="Monthly COGS and operating expenses chart"></canvas>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-slate-900">COGS full-year breakdown</h3>
                        <x-admin.expense-type-badge type="cogs" />
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-slate-500">
                                    <th class="pb-2 pr-3 font-medium">Item</th>
                                    <th class="pb-2 pr-3 font-medium text-right">Annual total</th>
                                    <th class="pb-2 font-medium text-right">% of COGS</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($cogsFullYear as $row)
                                    <tr>
                                        <td class="py-2.5 pr-3 font-medium text-slate-900">{{ $row['name'] }}</td>
                                        <td class="py-2.5 pr-3 text-right text-slate-900">{{ $formatRwf($row['amount']) }}</td>
                                        <td class="py-2.5 text-right text-slate-600">{{ number_format($row['percent'], 1) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-slate-500">No COGS categories configured.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold text-slate-900">Operating full-year breakdown</h3>
                        <x-admin.expense-type-badge type="operating" />
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-slate-500">
                                    <th class="pb-2 pr-3 font-medium">Item</th>
                                    <th class="pb-2 pr-3 font-medium text-right">Annual total</th>
                                    <th class="pb-2 font-medium text-right">% of operating</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($operatingFullYear as $row)
                                    <tr>
                                        <td class="py-2.5 pr-3 font-medium text-slate-900">{{ $row['name'] }}</td>
                                        <td class="py-2.5 pr-3 text-right text-slate-900">{{ $formatRwf($row['amount']) }}</td>
                                        <td class="py-2.5 text-right text-slate-600">{{ number_format($row['percent'], 1) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="py-6 text-center text-slate-500">No operating categories configured.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        @if ($tab === 'cogs')
        <div class="space-y-4">
            <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="tab" value="cogs">
                <input type="hidden" name="operating_month" value="{{ $operatingMonth }}">
                <div>
                    <label for="cogs_month" class="mb-1 block text-sm font-medium text-slate-700">Month</label>
                    <select id="cogs_month" name="cogs_month" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
                        @foreach ($monthNames as $index => $label)
                            @php $m = $index + 1; @endphp
                            <option value="{{ $m }}" @selected($cogsMonth === $m)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            @include('admin.expenses._month-table', [
                'title' => 'Cost of goods sold',
                'type' => 'cogs',
                'monthLabel' => $monthNames[$cogsMonth - 1],
                'breakdown' => $cogsMonthBreakdown,
            ])
        </div>
        @endif

        @if ($tab === 'operating')
        <div class="space-y-4">
            <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="tab" value="operating">
                <input type="hidden" name="cogs_month" value="{{ $cogsMonth }}">
                <div>
                    <label for="operating_month" class="mb-1 block text-sm font-medium text-slate-700">Month</label>
                    <select id="operating_month" name="operating_month" onchange="this.form.submit()" class="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-teal-600 focus:outline-none">
                        @foreach ($monthNames as $index => $label)
                            @php $m = $index + 1; @endphp
                            <option value="{{ $m }}" @selected($operatingMonth === $m)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            @include('admin.expenses._month-table', [
                'title' => 'Operating expenses',
                'type' => 'operating',
                'monthLabel' => $monthNames[$operatingMonth - 1],
                'breakdown' => $operatingMonthBreakdown,
            ])
        </div>
        @endif

        @if ($tab === 'add')
        <div class="space-y-6">
            <form method="POST" action="{{ route('admin.expenses.store') }}" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                @include('admin.expenses._form', [
                    'submitLabel' => 'Save expense',
                    'fromIndex' => true,
                    'formYear' => $year,
                ])
            </form>

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Recently added</h3>
                        <p class="text-sm text-slate-500">Last 10 expense entries (any period).</p>
                    </div>
                    <a href="{{ route('admin.expenses.index', ['year' => $year, 'tab' => 'all']) }}" class="text-sm font-medium text-teal-700 hover:underline">View all &rarr;</a>
                </div>
                <table data-bulk="{{ route('admin.bulk-delete', 'expenses') }}" class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Name</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Category</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Date</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-600">Amount</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600">By</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentExpenses as $expense)
                            <tr>
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $expense->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $expense->category?->name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $expense->expense_date->format('j M Y') }}</td>
                                <td class="px-4 py-3 text-right font-medium text-slate-900">{{ $formatRwf((float) $expense->amount) }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $expense->creator?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.expenses.edit', $expense) }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                                        <form method="POST" action="{{ route('admin.expenses.destroy', $expense) }}" onsubmit="return confirm('Delete this expense?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-slate-500">No expenses recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($tab === 'all')
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="text-base font-semibold text-slate-900">All expenses &mdash; {{ $year }}</h3>
                <p class="text-sm text-slate-500">{{ number_format($allExpensesCount) }} {{ $allExpensesCount === 1 ? 'entry' : 'entries' }} &middot; includes expenses and the raw materials each production batch consumed (COGS). Raw-material purchases are stock, not expenses, until production uses them.</p>
            </div>
            <table data-bulk="{{ route('admin.bulk-delete', 'expenses') }}" class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Name</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Category / Supplier</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Type</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Source</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Date</th>
                        <th class="px-4 py-3 text-right font-semibold text-slate-600">Amount</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">By</th>
                        <th class="px-4 py-3 text-left font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($allExpenses as $row)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $row['name'] }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['category'] }}</td>
                            <td class="px-4 py-3">
                                <x-admin.expense-type-badge :type="$row['type']" />
                            </td>
                            <td class="px-4 py-3">
                                @if ($row['source'] === 'production')
                                    <span class="inline-flex items-center rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 ring-1 ring-sky-200">Production</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 ring-1 ring-slate-200">Expense</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-600">{{ $row['date']->format('j M Y') }}</td>
                            <td class="px-4 py-3 text-right font-medium text-slate-900">{{ $formatRwf($row['amount']) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $row['by'] ?? '&mdash;' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <a href="{{ $row['edit_url'] }}" class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">{{ $row['source'] === 'production' ? 'View batch' : 'Edit' }}</a>
                                    @if ($row['source'] === 'expense')
                                        <form method="POST" action="{{ $row['delete_url'] }}" onsubmit="return confirm('Delete this expense?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-100">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-slate-500">No expenses for {{ $year }}.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($allExpensesCount > 0)
                <tfoot class="bg-slate-50 font-semibold text-slate-900">
                    <tr>
                        <td colspan="5" class="px-4 py-3">Total for {{ $year }} (all entries, not just this page)</td>
                        <td class="px-4 py-3 text-right">{{ $formatRwf($allExpensesTotal) }}</td>
                        <td colspan="2" class="px-4 py-3"></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
        <x-admin.table-pagination :paginator="$allExpenses" label="entries" />
        @endif
    </section>

    @if ($tab === 'overview')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            var canvas = document.getElementById('expensesMonthlyChart');
            if (!canvas || typeof Chart === 'undefined') return;

            var monthlyTotals = @json($monthlyTotals);

            new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: monthlyTotals.map(function (row) { return row.label; }),
                    datasets: [
                        {
                            label: 'COGS',
                            data: monthlyTotals.map(function (row) { return row.cogs_total; }),
                            backgroundColor: '#0F6E56',
                            borderColor: '#0B5A47',
                            borderWidth: 1,
                            borderRadius: 4,
                        },
                        {
                            label: 'Operating',
                            data: monthlyTotals.map(function (row) { return row.operating_total; }),
                            backgroundColor: '#534AB7',
                            borderColor: '#433AA0',
                            borderWidth: 1,
                            borderRadius: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    var v = ctx.parsed.y || 0;
                                    return ctx.dataset.label + ': ' + v.toLocaleString() + ' RWF';
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function (value) {
                                    return Number(value).toLocaleString();
                                },
                            },
                        },
                    },
                },
            });
        })();
    </script>
    @endif
</x-layouts.admin>
