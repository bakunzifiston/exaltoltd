<x-layouts.admin title="Financial reports">
    <section class="space-y-6">
        <div><a class="text-sm text-teal-700" href="{{ route('admin.reports.index') }}">Reports</a><h2 class="text-2xl font-semibold">Financial reports</h2>
        <p class="mt-2 text-sm text-slate-600">Create monthly income statements, annual comparisons, and actual or forecast financial tables. Saved drafts provide the same figures in preview, PDF and Excel.</p></div>
        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-lg bg-rose-50 p-4 text-rose-800">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('admin.reports.financial.store') }}" class="rounded-xl border bg-white p-5 space-y-4">
            @csrf
            <h3 class="font-semibold">Create a report</h3>
            <div class="grid gap-4 md:grid-cols-3">
                <label class="text-sm">Report title<input required name="title" maxlength="150" value="{{ old('title', 'Exalto Green Ltd Financial Report') }}" class="mt-1 w-full rounded-lg border p-2"></label>
                <label class="text-sm">Format<select name="kind" class="mt-1 w-full rounded-lg border p-2">@foreach(\App\Models\FinancialReport::selectableKinds() as $value => $label)<option value="{{ $value }}" @selected(old('kind')===$value)>{{ $label }}</option>@endforeach</select></label>
                <label class="text-sm">Mode<select name="mode" class="mt-1 w-full rounded-lg border p-2"><option value="actual">Actual</option><option value="forecast" @selected(old('mode')==='forecast')>Forecast</option></select></label>
                <label class="text-sm">Starting data<select name="source" class="mt-1 w-full rounded-lg border p-2"><option value="system">System snapshot (actuals only)</option><option value="manual" @selected(old('source')==='manual')>Blank template / manual figures</option></select></label>
                <label class="text-sm">First day of reporting year<input required type="date" name="start_date" value="{{ old('start_date', now()->startOfYear()->toDateString()) }}" class="mt-1 w-full rounded-lg border p-2"></label>
                <label class="text-sm">System data cutoff<input type="date" name="as_of" max="{{ now()->toDateString() }}" value="{{ old('as_of', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border p-2"></label>
                <label class="text-sm">Prepared by<input required name="prepared_by" maxlength="150" value="{{ old('prepared_by', auth()->user()->name) }}" class="mt-1 w-full rounded-lg border p-2"></label>
            </div>
            <p class="text-sm text-slate-600">Choose January for a calendar-year statement or May for the supplied financial table. Reports cover 12 months. Financial-table forecasts also include the next three reporting years. Forecasts start from a blank template.</p>
            <p class="text-sm text-amber-800">System cost of goods sold is the raw materials consumed in production batches, adjusted for finished goods still in stock at month end. Raw-material purchases are not expensed; materials not yet used stay in inventory. Review tax, depreciation, category assignments and ledger completeness. Historical workbook versions are not automatically merged.</p>
            <button class="rounded-lg bg-teal-700 px-4 py-2 font-semibold text-white">Create draft</button>
        </form>
        <div class="overflow-x-auto rounded-xl border bg-white"><table data-bulk="{{ route('admin.bulk-delete', 'financial-reports') }}" class="w-full text-left text-sm"><thead class="bg-slate-100"><tr><th class="p-3">Report</th><th class="p-3">Period start</th><th class="p-3">Mode / source</th><th class="p-3">Revision</th><th class="p-3">Updated</th><th class="p-3 text-right">Actions</th></tr></thead><tbody>
            @forelse($reports as $report)<tr class="border-t"><td class="p-3"><a class="font-semibold text-teal-700" href="{{ route('admin.reports.financial.show', $report) }}">{{ $report->title }}</a></td><td class="p-3">{{ $report->start_date->format('M Y') }}</td><td class="p-3">{{ ucfirst($report->mode) }} / {{ $report->source }}</td><td class="p-3">{{ $report->version }}</td><td class="p-3">{{ $report->updated_at->format('d M Y H:i') }}</td><td class="p-3 text-right"><form method="POST" action="{{ route('admin.reports.financial.destroy', $report) }}" onsubmit="return confirm(@js('Delete the draft "'.$report->title.'"? This cannot be undone.'))">@csrf @method('DELETE')<button type="submit" class="rounded-lg border border-rose-300 bg-white px-3 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Delete</button></form></td></tr>
            @empty<tr><td colspan="6" class="p-6 text-slate-500">No financial reports yet. Create the first draft above.</td></tr>@endforelse
        </tbody></table></div><x-admin.table-pagination :paginator="$reports" label="reports" />
    </section>
</x-layouts.admin>
