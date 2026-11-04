<x-layouts.admin title="Data Health">
    <section class="space-y-6">
        <div>
            <h2 class="text-2xl font-semibold text-slate-900">Data Health</h2>
            <p class="mt-1 text-sm text-slate-500">Records that are internally impossible — usually entered before stock validation existed. Correct these to fully trust the numbers.</p>
        </div>

        @if ($total === 0)
            <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-6 py-5 text-emerald-800 shadow-sm">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-emerald-100 text-lg font-bold">✓</span>
                <div>
                    <p class="font-semibold">All clear</p>
                    <p class="text-sm text-emerald-700">No stock inconsistencies found. Sales, production and inventory all reconcile.</p>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-6 py-4 text-rose-800 shadow-sm">
                <p class="font-semibold">{{ $total }} issue(s) found across {{ collect($sections)->filter(fn ($s) => $s['rows']->isNotEmpty())->count() }} categor(ies).</p>
                <p class="text-sm text-rose-700">Each links to the record so you can correct it. New entries are blocked by validation, so this list only shrinks.</p>
            </div>

            @foreach ($sections as $key => $section)
                @continue($section['rows']->isEmpty())
                <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-3">
                        <h3 class="text-sm font-semibold text-slate-800">{{ $section['label'] }} <span class="ml-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">{{ $section['rows']->count() }}</span></h3>
                        <p class="text-xs text-slate-400">{{ $section['hint'] }}</p>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-2 text-left">Record</th>
                                <th class="px-5 py-2 text-right">Figures</th>
                                <th class="px-5 py-2 text-right">Discrepancy</th>
                                <th class="px-5 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($section['rows'] as $row)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-5 py-2 font-medium text-slate-800">{{ $row['label'] }}</td>
                                    <td class="px-5 py-2 text-right text-slate-600">
                                        @if (isset($row['produced']) && isset($row['sold']))
                                            produced {{ number_format($row['produced'], 2) }} · sold {{ number_format($row['sold'], 2) }}
                                        @elseif (isset($row['in']) && isset($row['out']))
                                            in {{ number_format($row['in'], 2) }} · out {{ number_format($row['out'], 2) }}
                                        @elseif (isset($row['remaining']))
                                            produced {{ number_format($row['produced'], 2) }} · remaining {{ number_format($row['remaining'], 2) }}
                                        @endif
                                    </td>
                                    <td class="px-5 py-2 text-right font-semibold text-rose-700">+{{ number_format($row['over_by'], 2) }}</td>
                                    <td class="px-5 py-2 text-right"><a href="{{ $row['url'] }}" class="text-teal-700 hover:underline">Open</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </section>
</x-layouts.admin>
