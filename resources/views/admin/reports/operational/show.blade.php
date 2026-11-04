<x-layouts.admin :title="$report['title']">
    @php
        $fmt = fn ($value, string $format) => match (true) {
            $value === null => $format === 'text' || $format === 'date' ? '—' : 'n.a.',
            $format === 'money' => ($value < 0 ? '('.number_format(abs($value), 0).')' : number_format($value, 0)),
            $format === 'qty' => rtrim(rtrim(number_format($value, 2), '0'), '.'),
            $format === 'int' => number_format($value),
            $format === 'pct' => number_format($value * 100, 1).'%',
            $format === 'date' => \Carbon\Carbon::parse($value)->format('d M Y'),
            default => $value,
        };
        $period = ['start_date' => $from->toDateString(), 'end_date' => $to->toDateString()];
        $previewRows = 15;
    @endphp
    <section class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a class="text-sm text-teal-700" href="{{ route('admin.reports.index') }}">Reports</a>
                <h2 class="text-2xl font-semibold text-slate-900">{{ $report['title'] }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $report['subtitle'] }}</p>
            </div>
            <a href="{{ route('admin.reports.operational.export', ['type' => $type] + $period) }}" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Download Excel</a>
        </div>

        <nav class="flex flex-wrap gap-2" aria-label="Report type">
            @foreach (\App\Services\OperationalReportService::TYPES as $key => $label)
                <a href="{{ route('admin.reports.operational.show', ['type' => $key] + $period) }}"
                   class="rounded-lg border px-4 py-2 text-sm font-medium {{ $key === $type ? 'border-teal-700 bg-teal-700 text-white' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">{{ $label }}</a>
            @endforeach
        </nav>

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <label class="text-sm text-slate-600">From<input type="date" name="start_date" value="{{ $from->toDateString() }}" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">To<input type="date" name="end_date" value="{{ $to->toDateString() }}" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Apply</button>
            @if ($errors->any())<p class="text-sm text-rose-700">{{ $errors->first() }}</p>@endif
        </form>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800">Headline</h3>
            <dl class="grid grid-cols-1 text-sm md:grid-cols-2">
                @foreach ($report['headline'] as $metric)
                    <div class="flex items-baseline justify-between gap-4 border-b border-slate-100 px-4 py-3 {{ ! empty($metric['emphasis']) ? 'bg-emerald-50' : '' }}">
                        <dt class="text-slate-600">{{ $metric['label'] }}@if (! empty($metric['note']))<span class="block text-xs text-slate-400">{{ $metric['note'] }}</span>@endif</dt>
                        <dd class="whitespace-nowrap font-semibold tabular-nums text-slate-900">{{ $fmt($metric['value'], $metric['format']) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            @foreach ($report['tables'] as $table)
                <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <h3 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800">{{ $table['title'] }}</h3>
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">{{ $table['group_label'] }}</th>
                                @foreach ($table['measures'] as $measure)
                                    <th class="px-4 py-2 text-right font-semibold">{{ $measure['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse (array_slice($table['lines'], 0, $previewRows) as $line)
                                <tr>
                                    <td class="px-4 py-2 font-medium text-slate-900">{{ $line['label'] }}</td>
                                    @foreach ($table['measures'] as $measure)
                                        <td class="whitespace-nowrap px-4 py-2 {{ $measure['format'] === 'text' ? 'text-left' : 'text-right tabular-nums' }}">{{ $fmt($line['values'][$measure['key']], $measure['format']) }}</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="{{ count($table['measures']) + 1 }}" class="px-4 py-6 text-center text-slate-500">Nothing recorded in this period.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($table['lines'] !== [])
                            <tfoot class="bg-emerald-50 font-semibold text-slate-900">
                                <tr>
                                    <td class="px-4 py-2">Total</td>
                                    @foreach ($table['measures'] as $measure)
                                        <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums">{{ $table['total'][$measure['key']] === null ? '' : $fmt($table['total'][$measure['key']], $measure['format']) }}</td>
                                    @endforeach
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                    @if (count($table['lines']) > $previewRows)
                        <p class="border-t border-slate-100 px-4 py-2 text-xs text-slate-500">Showing the first {{ $previewRows }} of {{ count($table['lines']) }}. The total covers all of them; download Excel for the full list.</p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600 shadow-sm">
            <h3 class="mb-2 font-semibold text-slate-800">How to read this report</h3>
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($report['notes'] as $note)<li>{{ $note }}</li>@endforeach
            </ul>
            <p class="mt-3 text-xs text-slate-400">The Excel download adds the full detail behind every figure:
                @foreach ($report['sheets'] as $sheet){{ $sheet['name'] }} ({{ number_format(count($sheet['rows'])) }} rows){{ $loop->last ? '.' : ', ' }}@endforeach
            </p>
        </div>
    </section>
</x-layouts.admin>
