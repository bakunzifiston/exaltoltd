<x-layouts.admin title="Opening balances">
    @php($money = fn ($v) => number_format((float) $v, 2))
    <section class="space-y-6">
        @include('admin.finance._header', ['active' => 'opening-balances', 'heading' => 'Opening balances', 'intro' => 'The cut-over position every balance sheet starts from. The set must balance: assets less accumulated depreciation must equal liabilities plus equity.'])

        @if ($dates->isNotEmpty())
            <form method="GET" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <label class="text-sm text-slate-600">Saved cut-over date
                    <select name="as_of_date" onchange="this.form.submit()" class="mt-1 block rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($dates as $date)<option value="{{ $date }}" @selected($date === $selected)>{{ \Carbon\Carbon::parse($date)->format('d M Y') }}</option>@endforeach
                    </select>
                </label>
                @if ($check !== null)
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ abs($check) < 0.01 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ abs($check) < 0.01 ? 'Balances' : 'Out of balance by RWF '.$money($check) }}</span>
                @endif
            </form>
        @endif

        <form method="POST" action="{{ route('admin.finance.opening-balances.store') }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf
            <label class="block max-w-xs text-sm text-slate-600">Cut-over date (balances as at the end of this day)
                <input type="date" name="as_of_date" required value="{{ old('as_of_date', $selected) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </label>
            <div class="grid gap-6 md:grid-cols-3">
                @foreach (['Assets' => array_merge($assetLines, $contraLines), 'Liabilities' => $liabilityLines, 'Equity' => $equityLines] as $group => $keys)
                    <fieldset class="space-y-3">
                        <legend class="text-sm font-semibold text-slate-800">{{ $group }}</legend>
                        @foreach ($keys as $key)
                            <label class="block text-sm text-slate-600">{{ $lines[$key] }}@if (in_array($key, $contraLines, true)) <span class="text-xs text-slate-400">(deducted from assets)</span>@endif
                                <input type="number" step="0.01" name="amounts[{{ $key }}]" value="{{ old('amounts.'.$key, $amounts[$key] ?? '') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-right text-sm">
                            </label>
                        @endforeach
                    </fieldset>
                @endforeach
            </div>
            <label class="block text-sm text-slate-600">Notes
                <textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea>
            </label>
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Save opening balances</button>
                <p class="text-xs text-slate-500">Retained earnings is usually the balancing figure.</p>
            </div>
        </form>

        @if ($amounts !== [])
            <form method="POST" action="{{ route('admin.finance.opening-balances.destroy') }}" onsubmit="return confirm('Delete the whole opening balance set for this date?')">
                @csrf @method('DELETE')
                <input type="hidden" name="as_of_date" value="{{ $selected }}">
                <button type="submit" class="rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">Delete the set for {{ \Carbon\Carbon::parse($selected)->format('d M Y') }}</button>
            </form>
        @endif
    </section>
</x-layouts.admin>
