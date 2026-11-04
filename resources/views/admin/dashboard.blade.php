<x-layouts.admin title="Dashboard">
    @php
        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $fmt = fn ($n) => number_format($n, 0, '.', ',');
        $fmtRwf = fn ($n) => number_format($n, 0, '.', ',') . ' RWF';
    @endphp

    <section class="space-y-10 px-1">

        {{-- ========== HEADER + FILTERS ========== --}}
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between border-b border-slate-200 pb-6">
            <div class="flex flex-col gap-1">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">Dashboard</h2>
                <p class="text-sm text-slate-400">Real-time KPIs &amp; insights for your business</p>
            </div>
            <form method="GET" class="flex flex-wrap items-end gap-2">
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">Period</label>
                    <select name="period" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none">
                        @foreach (['today'=>'Today','yesterday'=>'Yesterday','last_7_days'=>'Last 7 Days','last_30_days'=>'Last 30 Days','this_month'=>'This Month','last_month'=>'Last Month','this_year'=>'This Year','all_time'=>'All Time'] as $k => $label)
                            <option value="{{ $k }}" @selected($period === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">From</label>
                    <input name="start_date" type="date" value="{{ $start_date }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none">
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-semibold uppercase tracking-widest text-slate-400">To</label>
                    <input name="end_date" type="date" value="{{ $end_date }}" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 outline-none">
                </div>
                <button type="submit" class="rounded-lg bg-teal-700 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-800 transition-colors">Apply</button>
                <a href="{{ route('admin.dashboard') }}" class="rounded-lg border border-slate-200 bg-white px-5 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50 transition-colors">Reset</a>
            </form>
        </div>

        {{-- ========== PAYMENTS AWAITING APPROVAL ========== --}}
        @can('approve-payments')
            @php
                $awaitingApproval = \App\Models\SalePaymentSubmission::query()->pending();
                $awaitingCount = (clone $awaitingApproval)->count();
            @endphp
            @if ($awaitingCount > 0)
                <a href="{{ route('admin.payment-approvals.index') }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-900 shadow-sm hover:bg-amber-100/70">
                    <span>
                        <span class="block text-sm font-semibold">{{ $awaitingCount }} payment{{ $awaitingCount === 1 ? '' : 's' }} awaiting approval</span>
                        <span class="block text-xs">{{ number_format((float) $awaitingApproval->sum('amount'), 0) }} RWF reported by the team — not counted as received until approved.</span>
                    </span>
                    <span class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white">Review</span>
                </a>
            @endif
        @endcan

        {{-- ========== ALERTS ========== --}}
        @if (($low_stock_count ?? 0) > 0 || ($expiring_count ?? 0) > 0 || ($overdue_ar_count ?? 0) > 0 || ($overdue_ap_count ?? 0) > 0)
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @if ($low_stock_count > 0)
                    <a href="{{ route('admin.inventory-records.stock') }}" class="flex items-center gap-3 rounded-xl border border-amber-200 border-l-4 border-l-amber-500 bg-gradient-to-r from-amber-50 to-amber-50/40 px-4 py-3 text-amber-900 shadow-sm transition hover:shadow-md hover:-translate-y-0.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 font-bold text-sm">!</span>
                        <div>
                            <p class="text-sm font-semibold">{{ $low_stock_count }} material(s)</p>
                            <p class="text-xs text-amber-700">At or below reorder level</p>
                        </div>
                    </a>
                @endif
                @if ($expiring_count > 0)
                    <a href="{{ route('admin.inventory-records.stock') }}" class="flex items-center gap-3 rounded-xl border border-rose-200 border-l-4 border-l-rose-500 bg-gradient-to-r from-rose-50 to-rose-50/40 px-4 py-3 text-rose-900 shadow-sm transition hover:shadow-md hover:-translate-y-0.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 font-bold text-sm">!</span>
                        <div>
                            <p class="text-sm font-semibold">{{ $expiring_count }} material(s)</p>
                            <p class="text-xs text-rose-700">Expiring within 30 days</p>
                        </div>
                    </a>
                @endif
                @if ($overdue_ar_count > 0)
                    <a href="{{ route('admin.customers.index') }}" class="flex items-center gap-3 rounded-xl border border-rose-200 border-l-4 border-l-rose-500 bg-gradient-to-r from-rose-50 to-rose-50/40 px-4 py-3 text-rose-900 shadow-sm transition hover:shadow-md hover:-translate-y-0.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 font-bold text-sm">$</span>
                        <div>
                            <p class="text-sm font-semibold">{{ $overdue_ar_count }} overdue</p>
                            <p class="text-xs text-rose-700">Customer receivables</p>
                        </div>
                    </a>
                @endif
                @if ($overdue_ap_count > 0)
                    <a href="{{ route('admin.suppliers.index') }}" class="flex items-center gap-3 rounded-xl border border-amber-200 border-l-4 border-l-amber-500 bg-gradient-to-r from-amber-50 to-amber-50/40 px-4 py-3 text-amber-900 shadow-sm transition hover:shadow-md hover:-translate-y-0.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 font-bold text-sm">!</span>
                        <div>
                            <p class="text-sm font-semibold">{{ $overdue_ap_count }} overdue</p>
                            <p class="text-xs text-amber-700">Supplier payables</p>
                        </div>
                    </a>
                @endif
            </div>
        @endif

        {{-- ========== SALES PERFORMANCE ========== --}}
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <span class="block h-5 w-1 rounded-full bg-teal-600"></span>
                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500">Sales Performance</h3>
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                {{-- Linked: all sales --}}
                <a href="{{ route('admin.sales.index') }}" class="group relative rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md hover:-translate-y-0.5 hover:border-teal-200">
                    <span class="absolute right-3 top-3 text-slate-200 text-xs transition group-hover:text-teal-400">↗</span>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Orders</p>
                    <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $fmt($total_orders) }}</p>
                    <div class="mt-3 h-0.5 w-8 rounded-full bg-slate-200 group-hover:bg-teal-400 transition-colors"></div>
                </a>
                {{-- Linked: delivery_status=Pending --}}
                <a href="{{ route('admin.sales.index', ['delivery_status' => 'Pending']) }}" class="group relative rounded-2xl border border-amber-100 bg-amber-50 p-5 shadow-sm transition hover:shadow-md hover:-translate-y-0.5 hover:border-amber-300">
                    <span class="absolute right-3 top-3 text-amber-200 text-xs transition group-hover:text-amber-500">↗</span>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-amber-600">Pending Delivery</p>
                    <p class="mt-2 text-2xl font-extrabold text-amber-900">{{ $fmt($pending_delivery_count) }}</p>
                    <div class="mt-3 h-0.5 w-8 rounded-full bg-amber-200 group-hover:bg-amber-500 transition-colors"></div>
                </a>
                {{-- Not linked: computed metric only --}}
                <div class="group rounded-2xl border border-teal-100 bg-teal-50 p-5 shadow-sm">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-teal-600">Avg Order Value</p>
                    <p class="mt-2 text-xl font-extrabold text-teal-900 leading-tight">{{ $fmtRwf($avg_order_value) }}</p>
                    <div class="mt-3 h-0.5 w-8 rounded-full bg-teal-200"></div>
                </div>
                {{-- Linked: all sales --}}
                <a href="{{ route('admin.sales.index') }}" class="group relative rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md hover:-translate-y-0.5 hover:border-teal-200">
                    <span class="absolute right-3 top-3 text-slate-200 text-xs transition group-hover:text-teal-400">↗</span>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Units Sold</p>
                    <p class="mt-2 text-2xl font-extrabold text-slate-900">{{ $fmt($total_units_sold) }}</p>
                    <div class="mt-3 h-0.5 w-8 rounded-full bg-slate-200 group-hover:bg-teal-400 transition-colors"></div>
                </a>
            </div>
        </div>

        {{-- ========== FINANCIAL HEALTH ========== --}}
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <span class="block h-5 w-1 rounded-full bg-emerald-600"></span>
                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500">Financial Health</h3>
            </div>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                {{-- Linked: all sales --}}
                <a href="{{ route('admin.sales.index') }}" class="group relative rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md hover:-translate-y-0.5 hover:border-emerald-200">
                    <span class="absolute right-3 top-3 text-slate-200 text-xs transition group-hover:text-emerald-400">↗</span>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Revenue (Total)</p>
                    <p class="mt-2 text-lg font-extrabold text-slate-900 leading-tight">{{ $fmtRwf($total_revenue_all) }}</p>
                    <div class="mt-3 h-0.5 w-8 rounded-full bg-slate-200 group-hover:bg-emerald-400 transition-colors"></div>
                </a>
                {{-- Linked: delivered sales, the recognised-revenue basis --}}
                <a href="{{ route('admin.sales.index', ['delivery_status' => 'Delivered']) }}" class="group relative rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md hover:-translate-y-0.5 hover:border-emerald-200">
                    <span class="absolute right-3 top-3 text-slate-200 text-xs transition group-hover:text-emerald-400">↗</span>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Revenue (Delivered)</p>
                    <p class="mt-2 text-lg font-extrabold text-slate-900 leading-tight">{{ $fmtRwf($revenue_recognised) }}</p>
                    <div class="mt-3 h-0.5 w-8 rounded-full bg-slate-200 group-hover:bg-emerald-400 transition-colors"></div>
                </a>
                {{-- Linked: customers with balance due --}}
                <a href="{{ route('admin.customers.index', ['balance' => 'due', 'sort' => 'outstanding', 'direction' => 'desc']) }}" class="group relative rounded-2xl border border-rose-100 bg-rose-50 p-5 shadow-sm transition hover:shadow-md hover:-translate-y-0.5 hover:border-rose-300">
                    <span class="absolute right-3 top-3 text-rose-200 text-xs transition group-hover:text-rose-500">↗</span>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-rose-500">AR (Customers Owe)</p>
                    <p class="mt-2 text-lg font-extrabold text-rose-900 leading-tight">{{ $fmtRwf($ar_balance) }}</p>
                    <div class="mt-3 h-0.5 w-8 rounded-full bg-rose-200 group-hover:bg-rose-500 transition-colors"></div>
                </a>
                {{-- Linked: expenses overview tab --}}
                <a href="{{ route('admin.expenses.index', ['tab' => 'overview']) }}" class="group relative rounded-2xl border border-emerald-100 bg-emerald-50 p-5 shadow-sm transition hover:shadow-md hover:-translate-y-0.5 hover:border-emerald-300">
                    <span class="absolute right-3 top-3 text-emerald-200 text-xs transition group-hover:text-emerald-500">↗</span>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-600">Total Expenses</p>
                    <p class="mt-2 text-lg font-extrabold text-emerald-900 leading-tight">{{ $fmtRwf($cogs + $operating_expenses) }}</p>
                    <div class="mt-3 h-0.5 w-8 rounded-full bg-emerald-200 group-hover:bg-emerald-500 transition-colors"></div>
                </a>
                {{-- Linked: expenses cogs tab --}}
                <a href="{{ route('admin.expenses.index', ['tab' => 'cogs']) }}" class="group relative rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md hover:-translate-y-0.5 hover:border-emerald-200">
                    <span class="absolute right-3 top-3 text-slate-200 text-xs transition group-hover:text-emerald-400">↗</span>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">COGS / Materials</p>
                    <p class="mt-2 text-lg font-extrabold text-slate-900 leading-tight">{{ $fmtRwf($cogs) }}</p>
                    <div class="mt-3 h-0.5 w-8 rounded-full bg-slate-200 group-hover:bg-emerald-400 transition-colors"></div>
                </a>
                {{-- Linked: expenses operating tab --}}
                <a href="{{ route('admin.expenses.index', ['tab' => 'operating']) }}" class="group relative rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md hover:-translate-y-0.5 hover:border-emerald-200">
                    <span class="absolute right-3 top-3 text-slate-200 text-xs transition group-hover:text-emerald-400">↗</span>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Operating Exp</p>
                    <p class="mt-2 text-lg font-extrabold text-slate-900 leading-tight">{{ $fmtRwf($operating_expenses) }}</p>
                    <div class="mt-3 h-0.5 w-8 rounded-full bg-slate-200 group-hover:bg-emerald-400 transition-colors"></div>
                </a>
                {{-- Not linked: computed metric --}}
                <div class="rounded-2xl border p-5 shadow-sm {{ $gross_profit >= 0 ? 'border-emerald-100 bg-emerald-50' : 'border-rose-100 bg-rose-50' }}">
                    <p class="text-[10px] font-bold uppercase tracking-widest {{ $gross_profit >= 0 ? 'text-emerald-600' : 'text-rose-500' }}">Gross Profit</p>
                    <p class="mt-2 text-lg font-extrabold leading-tight {{ $gross_profit >= 0 ? 'text-emerald-900' : 'text-rose-900' }}">{{ $fmtRwf($gross_profit) }}</p>
                    <p class="mt-1 text-xs font-semibold {{ $gross_profit >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">{{ $gross_margin_pct }}% margin</p>
                </div>
                {{-- Not linked: computed metric --}}
                <div class="rounded-2xl border p-5 shadow-sm {{ $net_profit >= 0 ? 'border-emerald-100 bg-emerald-50' : 'border-rose-100 bg-rose-50' }}">
                    <p class="text-[10px] font-bold uppercase tracking-widest {{ $net_profit >= 0 ? 'text-emerald-600' : 'text-rose-500' }}">Net Profit</p>
                    <p class="mt-2 text-lg font-extrabold leading-tight {{ $net_profit >= 0 ? 'text-emerald-900' : 'text-rose-900' }}">{{ $fmtRwf($net_profit) }}</p>
                    <p class="mt-1 text-xs font-semibold {{ $net_profit >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">{{ $net_margin_pct }}% margin</p>
                </div>
                {{-- Linked: suppliers sorted by balance owed --}}
                <a href="{{ route('admin.suppliers.index', ['sort' => 'total_owed', 'direction' => 'desc']) }}" class="group relative rounded-2xl border border-amber-100 bg-amber-50 p-5 shadow-sm transition hover:shadow-md hover:-translate-y-0.5 hover:border-amber-300">
                    <span class="absolute right-3 top-3 text-amber-200 text-xs transition group-hover:text-amber-500">↗</span>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-amber-600">AP (We Owe)</p>
                    <p class="mt-2 text-lg font-extrabold text-amber-900 leading-tight">{{ $fmtRwf($ap_balance) }}</p>
                    <div class="mt-3 h-0.5 w-8 rounded-full bg-amber-200 group-hover:bg-amber-500 transition-colors"></div>
                </a>
                {{-- Linked: paid sales --}}
                <a href="{{ route('admin.sales.index', ['payment_status' => 'Paid']) }}" class="group relative rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition hover:shadow-md hover:-translate-y-0.5 hover:border-emerald-200">
                    <span class="absolute right-3 top-3 text-slate-200 text-xs transition group-hover:text-emerald-400">↗</span>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Cash Collected</p>
                    <p class="mt-2 text-lg font-extrabold text-slate-900 leading-tight">{{ $fmtRwf($cash_collected) }}</p>
                    <div class="mt-3 h-0.5 w-8 rounded-full bg-slate-200 group-hover:bg-emerald-400 transition-colors"></div>
                </a>
            </div>
        </div>

        {{-- ========== CHARTS SECTION ========== --}}
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <span class="block h-5 w-1 rounded-full bg-blue-500"></span>
                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500">Analytics</h3>
            </div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">

                {{-- Pie: Revenue by Channel --}}
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Revenue by Channel</p>
                            <p class="text-xs text-slate-400 mt-0.5">Breakdown by sales type</p>
                        </div>
                        <a href="{{ route('admin.sales.index') }}" class="text-xs font-medium text-teal-600 hover:underline shrink-0 mt-0.5">View all →</a>
                    </div>
                    <div class="mt-4 h-52"><canvas id="chartRevenueChannel"></canvas></div>
                </div>

                {{-- Pie: Payment Status --}}
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Payment Status</p>
                            <p class="text-xs text-slate-400 mt-0.5">Orders by payment state</p>
                        </div>
                        <a href="{{ route('admin.sales.index') }}" class="text-xs font-medium text-teal-600 hover:underline shrink-0 mt-0.5">View all →</a>
                    </div>
                    <div class="mt-4 h-52"><canvas id="chartPaymentStatus"></canvas></div>
                </div>

                {{-- Horizontal Bar: Top Products by Revenue (no link per spec) --}}
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition md:col-span-2 xl:col-span-1">
                    <p class="text-sm font-semibold text-slate-700">Top Products by Revenue</p>
                    <p class="text-xs text-slate-400 mt-0.5">Highest-earning products</p>
                    <div class="mt-4 h-52"><canvas id="chartTopProducts"></canvas></div>
                </div>

                {{-- Vertical Bar: Stock on Hand by Product --}}
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Stock on Hand by Product</p>
                            <p class="text-xs text-slate-400 mt-0.5">Current finished goods inventory</p>
                        </div>
                        <a href="{{ route('admin.inventory-records.stock') }}" class="text-xs font-medium text-teal-600 hover:underline shrink-0 mt-0.5">View all →</a>
                    </div>
                    <div class="mt-4 h-52"><canvas id="chartStockOnHand"></canvas></div>
                </div>

                {{-- Vertical Bar: Production by Product --}}
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Production by Product</p>
                            <p class="text-xs text-slate-400 mt-0.5">Units produced per product</p>
                        </div>
                        <a href="{{ route('admin.productions.index') }}" class="text-xs font-medium text-teal-600 hover:underline shrink-0 mt-0.5">View all →</a>
                    </div>
                    <div class="mt-4 h-52"><canvas id="chartProductionProduct"></canvas></div>
                </div>

                {{-- Horizontal Bar: Top Customers by Revenue --}}
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Top Customers by Revenue</p>
                            <p class="text-xs text-slate-400 mt-0.5">Best-performing clients</p>
                        </div>
                        <a href="{{ route('admin.customers.index', ['sort' => 'lifetime_value', 'direction' => 'desc']) }}" class="text-xs font-medium text-teal-600 hover:underline shrink-0 mt-0.5">View all →</a>
                    </div>
                    <div class="mt-4 h-52"><canvas id="chartTopCustomers"></canvas></div>
                </div>

                {{-- Horizontal Bar: Top Suppliers by Spend --}}
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-700">Top Suppliers by Spend</p>
                            <p class="text-xs text-slate-400 mt-0.5">Highest procurement spend</p>
                        </div>
                        <a href="{{ route('admin.suppliers.index', ['sort' => 'total_owed', 'direction' => 'desc']) }}" class="text-xs font-medium text-teal-600 hover:underline shrink-0 mt-0.5">View all →</a>
                    </div>
                    <div class="mt-4 h-52"><canvas id="chartTopSuppliers"></canvas></div>
                </div>

            </div>
        </div>

        {{-- ========== MONTHLY TRENDS ========== --}}
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <span class="block h-5 w-1 rounded-full bg-violet-500"></span>
                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-500">Monthly Trends</h3>
            </div>
            <div class="grid gap-5 xl:grid-cols-3">
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <p class="text-sm font-semibold text-slate-700">Monthly Revenue</p>
                    <p class="text-xs text-slate-400 mt-0.5">Revenue earned per month</p>
                    <div class="mt-4 h-52"><canvas id="chartRevenue"></canvas></div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <p class="text-sm font-semibold text-slate-700">Monthly Production</p>
                    <p class="text-xs text-slate-400 mt-0.5">Units produced per month</p>
                    <div class="mt-4 h-52"><canvas id="chartProduction"></canvas></div>
                </div>
                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm hover:shadow-md transition">
                    <p class="text-sm font-semibold text-slate-700">Monthly Expenses</p>
                    <p class="text-xs text-slate-400 mt-0.5">Total spend per month</p>
                    <div class="mt-4 h-52"><canvas id="chartExpenses"></canvas></div>
                </div>
            </div>
        </div>

        {{-- Revenue vs Expenses Trend (full width) --}}
        <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm">
            <div class="flex items-center gap-3 mb-1">
                <span class="block h-4 w-1 rounded-full bg-teal-500"></span>
                <h3 class="text-sm font-semibold text-slate-700">Revenue vs Expenses Trend</h3>
            </div>
            <p class="text-xs text-slate-400 ml-4 mb-4">Year-over-year comparison</p>
            <div class="h-72"><canvas id="chartTrend"></canvas></div>
        </div>

    </section>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    <script>
        (function() {
            const baseChartOpts = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 11 }, padding: 16, usePointStyle: true }
                    }
                }
            };

            // 1. Revenue by Channel (Pie)
            const channelData = @json($by_channel);
            new Chart(document.getElementById('chartRevenueChannel'), {
                type: 'doughnut',
                data: {
                    labels: channelData.map(d => d.channel),
                    datasets: [{
                        data: channelData.map(d => d.revenue),
                        backgroundColor: ['#0d9488', '#cb8d20', '#ef4444', '#3b82f6', '#8b5cf6'],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6
                    }]
                },
                options: { ...baseChartOpts, cutout: '60%' }
            });

            // 2. Payment Status (Pie)
            const paymentData = @json($payment_status_breakdown);
            new Chart(document.getElementById('chartPaymentStatus'), {
                type: 'doughnut',
                data: {
                    labels: paymentData.map(d => d.status),
                    datasets: [{
                        data: paymentData.map(d => d.count),
                        backgroundColor: ['#10b981', '#cb8d20', '#64748b'],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6
                    }]
                },
                options: { ...baseChartOpts, cutout: '60%' }
            });

            const barOpts = (axis = 'x') => ({
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 }, ...(axis === 'y' ? { callback: v => v.toLocaleString() } : {}) }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { size: 11 }, ...(axis === 'x' ? { callback: v => v.toLocaleString() } : {}) }
                    }
                }
            });

            const hBarOpts = () => ({
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: v => v.toLocaleString(), font: { size: 11 } } },
                    y: { grid: { display: false }, ticks: { font: { size: 11 } } }
                }
            });

            // 3. Top Products by Revenue (Horizontal Bar)
            const topProducts = @json($top_products_by_revenue);
            new Chart(document.getElementById('chartTopProducts'), {
                type: 'bar',
                data: {
                    labels: topProducts.map(d => d.name),
                    datasets: [{ data: topProducts.map(d => d.revenue), backgroundColor: '#0d9488', borderRadius: 4 }]
                },
                options: hBarOpts()
            });

            // 4. Stock on Hand by Product (Vertical Bar)
            const stockData = @json($finished_goods_by_product);
            new Chart(document.getElementById('chartStockOnHand'), {
                type: 'bar',
                data: {
                    labels: stockData.map(d => d.name),
                    datasets: [{ data: stockData.map(d => d.remaining), backgroundColor: '#0F6E56', borderRadius: 4 }]
                },
                options: barOpts()
            });

            // 5. Production by Product (Vertical Bar)
            const prodData = @json($production_by_product);
            new Chart(document.getElementById('chartProductionProduct'), {
                type: 'bar',
                data: {
                    labels: prodData.map(d => d.label),
                    datasets: [{ data: prodData.map(d => d.value), backgroundColor: '#3b82f6', borderRadius: 4 }]
                },
                options: barOpts()
            });

            // 6. Top Customers by Revenue (Horizontal Bar)
            const custData = @json($top_customers);
            new Chart(document.getElementById('chartTopCustomers'), {
                type: 'bar',
                data: {
                    labels: custData.map(d => d.name),
                    datasets: [{ data: custData.map(d => d.revenue), backgroundColor: '#8b5cf6', borderRadius: 4 }]
                },
                options: hBarOpts()
            });

            // 7. Top Suppliers by Spend (Horizontal Bar)
            const suppData = @json($top_suppliers);
            new Chart(document.getElementById('chartTopSuppliers'), {
                type: 'bar',
                data: {
                    labels: suppData.map(d => d.name),
                    datasets: [{ data: suppData.map(d => d.spend), backgroundColor: '#cb8d20', borderRadius: 4 }]
                },
                options: hBarOpts()
            });

            // 8. Monthly Revenue (Bar)
            const monthlyRevenue = @json($monthly_revenue);
            new Chart(document.getElementById('chartRevenue'), {
                type: 'bar',
                data: {
                    labels: @json($month_labels ?? $months),
                    datasets: [{ data: monthlyRevenue, backgroundColor: '#0d9488', borderRadius: 4 }]
                },
                options: barOpts()
            });

            // 9. Monthly Production (Bar)
            const monthlyProduction = @json($monthly_production);
            new Chart(document.getElementById('chartProduction'), {
                type: 'bar',
                data: {
                    labels: @json($month_labels ?? $months),
                    datasets: [{ data: monthlyProduction, backgroundColor: '#0F6E56', borderRadius: 4 }]
                },
                options: barOpts()
            });

            // 10. Monthly Expenses (Bar)
            const monthlyExpenses = @json($monthly_expenses);
            new Chart(document.getElementById('chartExpenses'), {
                type: 'bar',
                data: {
                    labels: @json($month_labels ?? $months),
                    datasets: [{ data: monthlyExpenses, backgroundColor: '#cb8d20', borderRadius: 4 }]
                },
                options: barOpts()
            });

            // 11. Revenue vs Expenses Trend (Line)
            new Chart(document.getElementById('chartTrend'), {
                type: 'line',
                data: {
                    labels: @json($month_labels ?? $months),
                    datasets: [{
                        label: 'Revenue',
                        data: @json($monthly_revenue),
                        borderColor: '#0d9488',
                        backgroundColor: 'rgba(13, 148, 136, 0.08)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }, {
                        label: 'Expenses',
                        data: @json($monthly_expenses),
                        borderColor: '#cb8d20',
                        backgroundColor: 'rgba(245, 158, 11, 0.08)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { font: { size: 12 }, padding: 20, usePointStyle: true }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 12 } } },
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: v => v.toLocaleString(), font: { size: 12 } } }
                    }
                }
            });
        })();
    </script>
</x-layouts.admin>
