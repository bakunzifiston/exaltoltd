<x-layouts.admin title="Team Performance">
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Team Performance</h2>
            <p class="mt-1 text-sm text-slate-500">Who sold what, who collected the cash, and who produced what — by staff member.</p>
        </div>

        <form method="GET" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <select name="period" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" onchange="this.form.submit()">
                    @foreach (['today'=>'Today','last_7_days'=>'Last 7 Days','last_30_days'=>'Last 30 Days','this_month'=>'This Month','last_month'=>'Last Month','this_year'=>'This Year','all_time'=>'All Time','custom'=>'Custom range'] as $key => $label)
                        <option value="{{ $key }}" @selected($period === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input name="start_date" type="date" value="{{ $start_date }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <input name="end_date" type="date" value="{{ $end_date }}" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-xl border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Apply</button>
            </div>
            <p class="mt-2 text-xs text-slate-400">Custom range uses the two dates; other options ignore them.</p>
        </form>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.kpi-card label="Team Revenue (booked)" :value="number_format($totals['revenue'], 0) . ' RWF'" icon="banknotes" tone="teal" />
            <x-admin.kpi-card label="Cash Collected" :value="number_format($totals['cash_collected'], 0) . ' RWF'" icon="banknotes" tone="emerald" />
            <x-admin.kpi-card label="Transactions" :value="number_format($totals['orders'])" icon="chart" tone="violet" />
            <x-admin.kpi-card label="Units Produced" :value="number_format($totals['produced'], 2)" icon="factory" tone="gold" />
        </div>

        {{-- Sales leaderboard --}}
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-800">Sales Performance</h3>
                <p class="text-xs text-slate-400">Revenue is booked sales; cash collected is payments each person actually received in the period.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left">#</th>
                            <th class="px-4 py-2 text-left">Staff</th>
                            <th class="px-4 py-2 text-right">Orders</th>
                            <th class="px-4 py-2 text-right">Revenue</th>
                            <th class="px-4 py-2 text-right">Avg Sale</th>
                            <th class="px-4 py-2 text-right">Cash Collected</th>
                            <th class="px-4 py-2 text-right">Target</th>
                            <th class="px-4 py-2 text-left">Attainment</th>
                            <th class="px-4 py-2 text-right">Commission</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($salesRows as $i => $row)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-4 py-2 text-slate-400">{{ $i + 1 }}</td>
                                <td class="px-4 py-2">
                                    <span class="font-medium text-slate-800">{{ $row['user']->name }}</span>
                                    <x-admin.role-badge :role="$row['user']->role" :active="$row['user']->is_active" />
                                </td>
                                <td class="px-4 py-2 text-right">{{ number_format($row['orders']) }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ number_format($row['revenue'], 0) }} RWF</td>
                                <td class="px-4 py-2 text-right text-slate-600">{{ number_format($row['avg_sale'], 0) }} RWF</td>
                                <td class="px-4 py-2 text-right text-emerald-700">{{ number_format($row['cash_collected'], 0) }} RWF</td>
                                <td class="px-4 py-2 text-right text-slate-600">{{ $row['target'] > 0 ? number_format($row['target'], 0) . ' RWF' : '—' }}</td>
                                <td class="px-4 py-2">
                                    @if ($row['attainment'] !== null)
                                        @php($pct = min(100, $row['attainment']))
                                        <div class="flex items-center gap-2">
                                            <div class="h-2 w-20 overflow-hidden rounded-full bg-slate-100">
                                                <div class="h-full rounded-full {{ $row['attainment'] >= 100 ? 'bg-emerald-500' : ($row['attainment'] >= 60 ? 'bg-amber-500' : 'bg-rose-500') }}" style="width: {{ $pct }}%"></div>
                                            </div>
                                            <span class="text-xs font-medium {{ $row['attainment'] >= 100 ? 'text-emerald-700' : 'text-slate-600' }}">{{ $row['attainment'] }}%</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">no target</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right text-slate-600">{{ $row['commission'] > 0 ? number_format($row['commission'], 0) . ' RWF' : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-4 py-6 text-center text-slate-500">No attributed sales in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($unattributedSales > 0)
                <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400">
                    {{ $unattributedSales }} sale(s) in this period have no recorded staff member (e.g. online orders or pre-rollout records) and are excluded from the leaderboard.
                </p>
            @endif
        </div>

        {{-- Production output --}}
        <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-800">Production Output</h3>
                <p class="text-xs text-slate-400">Units produced and waste rate per worker assigned to each batch — measured even for staff who never log in.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left">#</th>
                            <th class="px-4 py-2 text-left">Worker</th>
                            <th class="px-4 py-2 text-right">Batches</th>
                            <th class="px-4 py-2 text-right">Units Produced</th>
                            <th class="px-4 py-2 text-right">Damaged</th>
                            <th class="px-4 py-2 text-right">Damage Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($productionRows as $i => $row)
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-4 py-2 text-slate-400">{{ $i + 1 }}</td>
                                <td class="px-4 py-2">
                                    <span class="font-medium text-slate-800">{{ $row['name'] }}</span>
                                    @if ($row['position'])<span class="ml-1 text-xs text-slate-400">{{ $row['position'] }}</span>@endif
                                </td>
                                <td class="px-4 py-2 text-right">{{ number_format($row['batches']) }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ number_format($row['produced'], 2) }}</td>
                                <td class="px-4 py-2 text-right text-slate-600">{{ number_format($row['damaged'], 2) }}</td>
                                <td class="px-4 py-2 text-right {{ $row['damage_rate'] > 5 ? 'font-semibold text-rose-700' : 'text-slate-600' }}">{{ $row['damage_rate'] }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No batches with an assigned worker in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($unattributedProduction > 0)
                <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-400">
                    {{ $unattributedProduction }} batch(es) in this period have no assigned worker and are excluded. Set "Responsible Worker" when recording a batch to include them.
                </p>
            @endif
        </div>
    </section>
</x-layouts.admin>
