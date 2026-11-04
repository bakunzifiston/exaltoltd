<x-layouts.admin title="Reports">
    <section class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-900">Reports</h2>
                <a href="{{ route('admin.reports.financial.index') }}" class="mt-3 inline-flex rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Financial reports: create, preview and download</a>
        <a class="mt-2 block text-sm font-semibold text-teal-700" href="{{ route('admin.reports.profitability') }}">Profitability by product and batch, plus lender ratios</a>
                <p class="mt-1 text-sm text-slate-500">Sales summary, revenue vs cost, profit summary, and inventory valuation.</p>
            </div>
        </div>

        {{-- Operational reports: on-screen preview plus Excel download in the house workbook style --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach ([
                'raw-materials' => ['Raw material report', 'Purchases, materials used in production (cost of goods), stock left, suppliers and batches.'],
                'sales' => ['Sales report', 'Sales by product, customer, channel and month, with cost of goods and gross profit.'],
                'production' => ['Production report', 'Batches, units produced, damaged and sold, and raw-material cost per unit.'],
                'returns' => ['Stock returns report', 'Goods returned by product, customer, reason and date; sellable versus damaged, expired or rejected.'],
                'samples' => ['Samples report', 'Samples given by product, batch, recipient and date, with their internal cost.'],
                'customer-balances' => ['Customer balances', 'What each customer owes after sales, returns and payments.'],
                'stock-movements' => ['Finished goods movements', 'Every movement of finished goods: production, sales, returns, write-offs and samples.'],
            ] as $key => [$label, $blurb])
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <h3 class="text-sm font-semibold text-slate-800">{{ $label }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ $blurb }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <a href="{{ route('admin.reports.operational.show', $key) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                        <a href="{{ route('admin.reports.operational.export', $key) }}" class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-800">Download Excel</a>
                    </div>
                </div>
            @endforeach
        </div>

        <form method="GET" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <select name="period" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                    @foreach (['today'=>'Today','yesterday'=>'Yesterday','last_7_days'=>'Last 7 Days','last_30_days'=>'Last 30 Days','this_month'=>'This Month','last_month'=>'Last Month','this_year'=>'This Year','all_time'=>'All Time'] as $key => $label)
                        <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input name="start_date" type="date" value="{{ $start_date }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <input name="end_date" type="date" value="{{ $end_date }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Apply</button>
            </div>
        </form>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.kpi-card label="Total Revenue" :value="number_format($stats['total_revenue'], 0) . ' RWF'" icon="banknotes" tone="emerald" />
            <x-admin.kpi-card label="Net Profit" :value="number_format($stats['net_profit'], 0) . ' RWF'" icon="chart" :tone="$stats['net_profit'] >= 0 ? 'emerald' : 'rose'" />
            <x-admin.kpi-card label="Cash Collected" :value="number_format($stats['cash_collected'], 0) . ' RWF'" icon="banknotes" tone="teal" />
            <x-admin.kpi-card label="Raw Material Stock Value" :value="number_format($stats['fifo_stock_value'], 0) . ' RWF'" icon="banknotes" tone="gold" />
        </div>

        {{-- Profit & Loss summary --}}
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-800">Profit &amp; Loss Summary</h3>
                <p class="text-xs text-slate-400">Revenue (Paid sales) − cost of goods sold − operating expenses, for the selected period.</p>
            </div>
            <dl class="divide-y divide-slate-100 text-sm">
                <div class="flex items-center justify-between px-4 py-3">
                    <dt class="text-slate-600">Revenue (Paid sales)</dt>
                    <dd class="font-medium text-slate-900">{{ number_format($stats['total_revenue'], 0) }} RWF</dd>
                </div>
                <div class="flex items-center justify-between px-4 py-3">
                    <dt class="text-slate-600">− Cost of Goods Sold <span class="text-xs text-slate-400">(raw materials consumed in production, less finished goods still in stock)</span></dt>
                    <dd class="font-medium text-rose-700">({{ number_format($stats['total_revenue'] - $stats['gross_profit'], 0) }}) RWF</dd>
                </div>
                <div class="flex items-center justify-between bg-slate-50/70 px-4 py-3">
                    <dt class="font-medium text-slate-700">= Gross Profit</dt>
                    <dd class="font-semibold text-slate-900">{{ number_format($stats['gross_profit'], 0) }} RWF</dd>
                </div>
                <div class="flex items-center justify-between px-4 py-3">
                    <dt class="text-slate-600">− Operating Expenses <span class="text-xs text-slate-400">(rent, salaries, energy… — excludes raw materials)</span></dt>
                    <dd class="font-medium text-rose-700">({{ number_format($stats['expenses_total'], 0) }}) RWF</dd>
                </div>
                <div class="flex items-center justify-between {{ $stats['net_profit'] >= 0 ? 'bg-emerald-50' : 'bg-rose-50' }} px-4 py-3">
                    <dt class="font-semibold text-slate-800">= Net Profit</dt>
                    <dd class="text-base font-bold {{ $stats['net_profit'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ number_format($stats['net_profit'], 0) }} RWF</dd>
                </div>
                <div class="flex items-center justify-between px-4 py-2.5 text-xs text-slate-500">
                    <dt>Memo: stock purchased this period (cash out, not an extra expense)</dt>
                    <dd>{{ number_format($stats['purchases_total'] ?? 0, 0) }} RWF</dd>
                </div>
            </dl>
            <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400">
                Cost of goods sold is derived automatically from the raw materials production batches consumed. Raw-material purchases are an inventory asset and are <strong>not</strong> double-counted as expenses.
            </p>
        </div>

        {{-- Receivables / payables aging --}}
        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            @foreach ([['title' => 'Accounts Receivable Aging', 'subtitle' => 'Money customers still owe, by how overdue it is.', 'rows' => $ar_aging], ['title' => 'Accounts Payable Aging', 'subtitle' => 'Money owed to suppliers, by how overdue it is.', 'rows' => $ap_aging]] as $aging)
                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-4 py-3">
                        <h3 class="text-sm font-semibold text-slate-800">{{ $aging['title'] }}</h3>
                        <p class="text-xs text-slate-400">{{ $aging['subtitle'] }}</p>
                    </div>
                    @php($agingTotal = collect($aging['rows'])->sum('amount'))
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Bucket</th>
                                <th class="px-4 py-2 text-right">Invoices</th>
                                <th class="px-4 py-2 text-right">Amount</th>
                                <th class="px-4 py-2 text-right">Share</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($aging['rows'] as $key => $bucket)
                                <tr class="{{ in_array($key, ['61_90', '90_plus'], true) && $bucket['amount'] > 0 ? 'bg-rose-50/50' : '' }}">
                                    <td class="px-4 py-2">{{ $bucket['label'] }}</td>
                                    <td class="px-4 py-2 text-right">{{ $bucket['count'] }}</td>
                                    <td class="px-4 py-2 text-right font-medium {{ $bucket['amount'] > 0 && $key !== 'current' ? 'text-rose-700' : 'text-slate-700' }}">{{ number_format($bucket['amount'], 0) }} RWF</td>
                                    <td class="px-4 py-2 text-right text-slate-500">{{ $agingTotal > 0 ? number_format($bucket['amount'] / $agingTotal * 100, 0) . '%' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-slate-200 bg-slate-50 font-semibold">
                                <td class="px-4 py-2">Total outstanding</td>
                                <td class="px-4 py-2 text-right">{{ collect($aging['rows'])->sum('count') }}</td>
                                <td class="px-4 py-2 text-right">{{ number_format($agingTotal, 0) }} RWF</td>
                                <td class="px-4 py-2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-slate-800">Sales Summary by Product</h3>
                </div>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Product</th>
                            <th class="px-4 py-2 text-left">Quantity</th>
                            <th class="px-4 py-2 text-left">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($sales_by_product as $row)
                            <tr>
                                <td class="px-4 py-2">{{ $row->product_name }}</td>
                                <td class="px-4 py-2">{{ number_format($row->total_qty, 2) }}</td>
                                <td class="px-4 py-2">{{ number_format($row->total_revenue, 0) }} RWF</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-slate-500">No data for selected period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 py-3">
                    <h3 class="text-sm font-semibold text-slate-800">Inventory Valuation</h3>
                </div>
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left">Item</th>
                            <th class="px-4 py-2 text-left">Qty On Hand</th>
                            <th class="px-4 py-2 text-left">Avg Cost</th>
                            <th class="px-4 py-2 text-left">Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($inventory_valuation as $row)
                            <tr>
                                <td class="px-4 py-2">{{ $row['item_name'] }}</td>
                                <td class="px-4 py-2">{{ number_format($row['qty_on_hand'], 2) }}</td>
                                <td class="px-4 py-2">{{ number_format($row['avg_cost'], 0) }} RWF</td>
                                <td class="px-4 py-2">{{ number_format($row['value'], 0) }} RWF</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-500">No inventory valuation data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</x-layouts.admin>
