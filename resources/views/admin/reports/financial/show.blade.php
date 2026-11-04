<x-layouts.admin title="Financial report preview">
    @php($money = fn ($v) => $v === null ? 'n.a.' : ($v < 0 ? '('.number_format(abs($v), 2).')' : number_format($v, 2)))
    @php($exportParams = ['financialReport' => $report, 'compare_id' => $prior?->id])
    <section class="space-y-5">
        <a class="text-sm text-teal-700" href="{{ route('admin.reports.financial.index') }}">Financial reports</a>
        <div class="flex flex-wrap items-center justify-between gap-4"><div><h2 class="text-2xl font-semibold">{{ $report->title }}</h2><p class="text-sm text-slate-600">{{ ucfirst($report->mode) }} draft · {{ $report->start_date->format('M Y') }} – {{ $report->start_date->copy()->addMonths(11)->format('M Y') }} · RWF · Revision {{ $report->version }} · Prepared by {{ $report->prepared_by }}</p></div>
        <div class="flex flex-wrap gap-2"><a class="rounded-lg border px-4 py-2" href="{{ route('admin.reports.financial.edit', $report) }}">Edit figures</a><a class="rounded-lg border px-4 py-2" target="_blank" rel="noopener" href="{{ route('admin.reports.financial.export', $exportParams + ['format' => 'pdf', 'preview' => 1]) }}">PDF preview</a><a class="rounded-lg bg-teal-700 px-4 py-2 text-white" href="{{ route('admin.reports.financial.export', $exportParams + ['format' => 'pdf']) }}">Download PDF</a><a class="rounded-lg bg-teal-700 px-4 py-2 text-white" href="{{ route('admin.reports.financial.export', $exportParams + ['format' => 'xlsx']) }}">Download Excel</a><form method="POST" action="{{ route('admin.reports.financial.destroy', $report) }}" onsubmit="return confirm('Delete this draft? This cannot be undone.')">@csrf @method('DELETE')<button type="submit" class="rounded-lg border border-rose-300 bg-white px-4 py-2 text-rose-700 hover:bg-rose-50">Delete draft</button></form></div></div>
        @if(session('status'))<p class="rounded-lg bg-teal-50 p-3 text-teal-800">{{ session('status') }}</p>@endif
        @if($errors->any())<p class="rounded-lg bg-rose-50 p-3 text-rose-800">{{ $errors->first() }}</p>@endif
        @if(!empty($calculation['checks']))
            <div class="rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm text-rose-900">
                <p class="font-semibold">This statement does not balance.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach($calculation['checks'] as $check)
                        <li>{{ $check['label'] }} is {{ $money($check['amount']) }} in {{ $calculation['columns'][$check['column']] ?? 'an unknown period' }}, not zero.</li>
                    @endforeach
                </ul>
                <p class="mt-2">Check the opening balances and that every subledger is complete before using these figures.</p>
            </div>
        @endif
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $calculation['missing'] }} missing monthly or forecast inputs. “n.a.” means a required amount is missing; it is not zero. This is a management draft pending review of the reporting basis below.</div>
        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4"><label class="text-sm">Compare with earlier saved report<select name="compare_id" class="mt-1 block rounded-lg border p-2"><option value="">No comparison</option>@foreach($comparables as $candidate)@if($candidate->start_date->month === $report->start_date->month && $candidate->start_date->lt($report->start_date))<option value="{{ $candidate->id }}" @selected($prior?->id === $candidate->id)>{{ $candidate->title }} ({{ $candidate->start_date->format('M Y') }}, v{{ $candidate->version }})</option>@endif @endforeach</select></label><button class="rounded-lg border px-4 py-2">Apply comparison</button></form>
        @if($prior)
            <div class="overflow-auto rounded-lg border bg-white"><table class="w-full text-sm"><caption class="p-3 text-left font-semibold">First 12 months: {{ $prior->title }} vs {{ $report->title }}. Change % uses the absolute prior amount; zero or missing prior totals are n.a.</caption><thead class="bg-slate-100"><tr><th class="p-3 text-left">Metric</th><th class="p-3 text-right">{{ $prior->start_date->format('M Y') }}</th><th class="p-3 text-right">{{ $report->start_date->format('M Y') }}</th><th class="p-3 text-right">Change (RWF)</th><th class="p-3 text-right">Change %</th></tr></thead><tbody>@foreach($comparison as $row)<tr class="border-t"><td class="p-3">{{ $row['label'] }}</td><td class="p-3 text-right">{{ $money($row['prior']) }}</td><td class="p-3 text-right">{{ $money($row['current']) }}</td><td class="p-3 text-right">{{ $money($row['change']) }}</td><td class="p-3 text-right">{{ $row['growth'] === null ? 'n.a.' : number_format($row['growth'] * 100, 1).'%' }}</td></tr>@endforeach</tbody></table></div>
        @endif
        <div class="overflow-auto rounded-xl border bg-white"><table class="w-full whitespace-nowrap text-sm"><thead class="bg-slate-800 text-white"><tr><th class="sticky left-0 bg-slate-800 p-3 text-left">Description</th>@foreach($calculation['columns'] as $column)<th class="p-3 text-right">{{ $column }}</th>@endforeach<th class="p-3 text-right">{{ $calculation['annual_mode'] === 'closing' ? 'Closing position' : 'First 12 months' }}</th><th class="p-3 text-right">{{ ['revenue' => '% of revenue', 'total_assets' => '% of total assets'][$calculation['margin_base']] ?? '' }}</th></tr></thead><tbody>
        @foreach($calculation['lines'] as $line)
            @if($line['type'] === 'heading')<tr class="bg-slate-100"><th class="p-3 text-left" colspan="{{ count($calculation['columns']) + 3 }}">{{ $line['label'] }}</th></tr>
            @else<tr class="border-t {{ $line['type'] === 'check' ? 'bg-rose-50 font-semibold text-rose-900' : ($line['type'] === 'result' ? 'bg-teal-50 font-semibold' : ($line['type'] === 'total' ? 'bg-slate-50 font-semibold' : '')) }}"><td class="p-3">{{ $line['label'] }}</td>@foreach($line['values'] as $value)<td class="p-3 text-right tabular-nums">{{ $money($value) }}</td>@endforeach<td class="p-3 text-right tabular-nums">{{ $money($line['annual']) }}</td><td class="p-3 text-right">{{ $line['margin'] === null ? 'n.a.' : number_format($line['margin'] * 100, 1).'%' }}</td></tr>@endif
        @endforeach
        </tbody></table></div>
        <div class="rounded-xl border bg-white p-5"><h3 class="font-semibold">Reporting basis and source notes</h3><ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-slate-600">@foreach($report->source_notes as $note)<li>{{ $note }}</li>@endforeach</ul>@if($report->notes)<p class="mt-4 whitespace-pre-wrap text-sm">{{ $report->notes }}</p>@endif</div>
    </section>
</x-layouts.admin>
