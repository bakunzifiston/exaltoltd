<x-layouts.admin title="Profitability and ratios">
    @php($money = fn ($v) => $v === null ? 'n.a.' : ($v < 0 ? '('.number_format(abs($v), 2).')' : number_format($v, 2)))
    <section class="space-y-5">
        <div>
            <a class="text-sm text-teal-700" href="{{ route('admin.reports.index') }}">Reports</a>
            <h2 class="text-2xl font-semibold">Profitability and ratios</h2>
            <p class="mt-2 text-sm text-slate-600">
                Gross margin per product and per production batch, using the cost frozen onto each sale
                line (the raw-material cost per unit of the batch it came from), plus the leverage, cover and liquidity ratios lenders ask for. Delivered sales only.
            </p>
        </div>

        @if(session('status'))<p class="rounded-lg bg-teal-50 p-3 text-sm text-teal-800">{{ session('status') }}</p>@endif
        @if($errors->any())<p class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</p>@endif

        <div class="flex flex-wrap items-end justify-between gap-4 rounded-xl border bg-white p-4">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                <label class="text-sm">From<input type="date" name="from" value="{{ $from }}" class="mt-1 block rounded-lg border p-2"></label>
                <label class="text-sm">To<input type="date" name="to" value="{{ $to }}" class="mt-1 block rounded-lg border p-2"></label>
                <button class="rounded-lg border px-4 py-2 text-sm">Apply</button>
            </form>
            <form method="POST" action="{{ route('admin.reports.profitability.recost') }}">
                @csrf
                <input type="hidden" name="from" value="{{ $from }}">
                <input type="hidden" name="to" value="{{ $to }}">
                <button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Recalculate costs</button>
            </form>
        </div>

        @if($summary['uncosted_lines'] > 0)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                {{ $summary['uncosted_lines'] }} delivered sale lines have no cost. They contribute revenue with no cost
                against them, so any total that includes them would flatter the margin. Rows affected show
                <strong>n.a.</strong> rather than a misleading figure. Use <strong>Recalculate costs</strong>, and check that the
                products sold have matching purchase records.
            </div>
        @endif

        @if(!empty($costWarnings))
            <div class="rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm text-rose-900">
                <p class="font-semibold">{{ count($costWarnings) }} production batches have an implausibly low cost, so the margins below are overstated.</p>
                <p class="mt-1">
                    These batches record fewer raw materials than the units they claim to have produced, which makes the goods
                    look almost free to make. It usually means a legacy or opening-stock batch was created to hold finished
                    goods without recording what went into them. Fix the batch materials to get a true margin.
                </p>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-left">
                        <thead><tr><th class="py-1 pr-4">Batch</th><th class="py-1 pr-4">Product</th><th class="py-1 pr-4 text-right">Cost/unit</th><th class="py-1 pr-4 text-right">Sold at</th><th class="py-1 text-right">Cost as % of price</th></tr></thead>
                        <tbody>
                        @foreach($costWarnings as $warning)
                            <tr class="border-t border-rose-200">
                                <td class="py-1 pr-4 font-medium">{{ $warning['batch_id'] }}</td>
                                <td class="py-1 pr-4">{{ $warning['product'] }}</td>
                                <td class="py-1 pr-4 text-right tabular-nums">{{ number_format($warning['unit_cost'], 2) }}</td>
                                <td class="py-1 pr-4 text-right tabular-nums">{{ number_format($warning['unit_price'], 2) }}</td>
                                <td class="py-1 text-right tabular-nums">{{ $warning['cost_share'] }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs">Flagged where recorded cost is under 5% of the price achieved. That is a prompt to check, not proof the figure is wrong.</p>
            </div>
        @endif

        <div class="grid gap-4 md:grid-cols-4">
            @foreach([
                ['Revenue', $money($summary['revenue'])],
                ['Cost of goods sold', $money($summary['cost'])],
                ['Gross margin', $money($summary['margin'])],
                ['Margin %', $summary['margin_pct'] === null ? 'n.a.' : $summary['margin_pct'].'%'],
            ] as [$label, $value])
                <div class="rounded-xl border bg-white p-5">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $label }}</p>
                    <p class="mt-2 text-lg font-extrabold tabular-nums text-slate-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full text-left text-sm">
                <caption class="p-3 text-left font-semibold">Margin by product</caption>
                <thead class="bg-slate-100"><tr><th class="p-3">Product</th><th class="p-3 text-right">Units</th><th class="p-3 text-right">Revenue</th><th class="p-3 text-right">Cost</th><th class="p-3 text-right">Margin</th><th class="p-3 text-right">Margin %</th></tr></thead>
                <tbody>
                @forelse($products as $row)
                    <tr class="border-t">
                        <td class="p-3 font-medium">{{ $row['label'] }}@if($row['uncosted_lines'] > 0)<span class="block text-xs text-amber-700">{{ $row['uncosted_lines'] }} uncosted lines</span>@endif</td>
                        <td class="p-3 text-right tabular-nums">{{ number_format($row['quantity'], 2) }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $money($row['revenue']) }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $money($row['cost']) }}</td>
                        <td class="p-3 text-right font-semibold tabular-nums">{{ $money($row['margin']) }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $row['margin_pct'] === null ? 'n.a.' : $row['margin_pct'].'%' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-slate-500">No delivered sales in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full text-left text-sm">
                <caption class="p-3 text-left font-semibold">Margin by production batch</caption>
                <thead class="bg-slate-100"><tr><th class="p-3">Batch</th><th class="p-3">Produced</th><th class="p-3 text-right">Units sold</th><th class="p-3 text-right">Revenue</th><th class="p-3 text-right">Cost</th><th class="p-3 text-right">Margin</th><th class="p-3 text-right">Margin %</th></tr></thead>
                <tbody>
                @forelse($batches as $row)
                    <tr class="border-t">
                        <td class="p-3 font-medium">{{ $row['label'] }}</td>
                        <td class="p-3">{{ $row['production_date'] ? \Illuminate\Support\Carbon::parse($row['production_date'])->format('d M Y') : '—' }}</td>
                        <td class="p-3 text-right tabular-nums">{{ number_format($row['quantity'], 2) }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $money($row['revenue']) }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $money($row['cost']) }}</td>
                        <td class="p-3 text-right font-semibold tabular-nums">{{ $money($row['margin']) }}</td>
                        <td class="p-3 text-right tabular-nums">{{ $row['margin_pct'] === null ? 'n.a.' : $row['margin_pct'].'%' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-slate-500">No delivered sales in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full text-left text-sm">
                <caption class="p-3 text-left font-semibold">
                    Ratios at {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}
                    <span class="block text-xs font-normal text-slate-500">A ratio with a missing or zero denominator reads n.a. It is never shown as zero.</span>
                </caption>
                <thead class="bg-slate-100"><tr><th class="p-3">Ratio</th><th class="p-3">Group</th><th class="p-3 text-right">Value</th><th class="p-3">What it means</th></tr></thead>
                <tbody>
                @foreach($ratios as $ratio)
                    <tr class="border-t">
                        <td class="p-3 font-medium">{{ $ratio['name'] }}</td>
                        <td class="p-3 text-slate-600">{{ $ratio['group'] }}</td>
                        <td class="p-3 text-right font-semibold tabular-nums">
                            {{ $ratio['value'] === null ? 'n.a.' : number_format($ratio['value'], $ratio['unit'] === '%' ? 1 : 2).($ratio['unit'] === '%' ? '%' : '') }}
                        </td>
                        <td class="p-3 text-slate-600">{{ $ratio['explanation'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
