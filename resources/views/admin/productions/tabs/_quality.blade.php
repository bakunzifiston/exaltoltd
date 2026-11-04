<div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
    <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-slate-800">Release status</h3>
        <p class="text-sm text-slate-600">Currently <strong>{{ strtolower(\App\Models\Production::QC_STATUSES[$production->qc_status] ?? $production->qc_status) }}</strong>@if ($production->released_at) — set {{ $production->released_at->format('d M Y H:i') }}@if ($production->releasedBy) by {{ $production->releasedBy->name }}@endif @endif.</p>
        @if ($production->release_notes)<p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $production->release_notes }}</p>@endif
        <p class="text-xs text-slate-500">Only released batches can be sold, in the shop and on the sales form. Batches past their use-by date are blocked whatever their status.</p>
        @can('manage-all-production')
            <form method="POST" action="{{ route('admin.productions.release', $production) }}" class="space-y-3">
                @csrf
                <label class="block text-sm text-slate-600">Set status
                    <select name="qc_status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach (\App\Models\Production::QC_STATUSES as $value => $label)<option value="{{ $value }}" @selected($production->qc_status === $value)>{{ $label }}</option>@endforeach</select>
                </label>
                <label class="block text-sm text-slate-600">Reason / notes <span class="text-xs text-slate-400">(required to hold or reject)</span>
                    <textarea name="release_notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('release_notes') }}</textarea>
                </label>
                <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Save status</button>
            </form>
        @else
            <p class="text-xs text-slate-500">A manager or the owner releases, holds or rejects batches.</p>
        @endcan
    </div>

    <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
        <h3 class="text-sm font-semibold text-slate-800">Record a quality check</h3>
        <form method="POST" action="{{ route('admin.productions.quality-checks.store', $production) }}" class="grid gap-3 md:grid-cols-3">
            @csrf
            <label class="text-sm text-slate-600">Check<select name="check_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach (\App\Models\BatchQualityCheck::TYPES as $value => $label)<option value="{{ $value }}" @selected(old('check_type') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Checked at<input type="datetime-local" name="checked_at" required value="{{ old('checked_at', now()->format('Y-m-d\TH:i')) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Result<select name="result" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach (\App\Models\BatchQualityCheck::RESULTS as $value => $label)<option value="{{ $value }}" @selected(old('result') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Measured value<input type="number" step="any" name="measured_value" value="{{ old('measured_value') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Unit<input name="unit" maxlength="20" value="{{ old('unit') }}" placeholder="e.g. pH, °C, °Bx" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Lab report ref.<input name="lab_report_ref" maxlength="100" value="{{ old('lab_report_ref') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Spec minimum<input type="number" step="any" name="spec_min" value="{{ old('spec_min') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Spec maximum<input type="number" step="any" name="spec_max" value="{{ old('spec_max') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Notes<input name="notes" maxlength="2000" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <div class="md:col-span-3"><button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Add check</button></div>
        </form>
    </div>
</div>

<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-2">Checked</th><th class="px-4 py-2">Check</th><th class="px-4 py-2 text-right">Measured</th><th class="px-4 py-2">Specification</th><th class="px-4 py-2">Result</th><th class="px-4 py-2">Lab report</th><th class="px-4 py-2">By</th><th class="px-4 py-2">Notes</th><th class="px-4 py-2"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($checks as $check)
                <tr>
                    <td class="whitespace-nowrap px-4 py-2">{{ $check->checked_at->format('d M Y H:i') }}</td>
                    <td class="px-4 py-2">{{ $check->typeLabel() }}</td>
                    <td class="px-4 py-2 text-right tabular-nums {{ $check->isOutOfSpec() ? 'font-semibold text-rose-700' : '' }}">{{ $check->measured_value !== null ? (float) $check->measured_value.' '.$check->unit : '—' }}@if ($check->isOutOfSpec())<span class="block text-xs">out of spec</span>@endif</td>
                    <td class="px-4 py-2 text-slate-600">{{ trim(($check->spec_min !== null ? 'min '.(float) $check->spec_min : '').' '.($check->spec_max !== null ? 'max '.(float) $check->spec_max : '')) ?: '—' }}</td>
                    <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 {{ $check->result === 'pass' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-rose-50 text-rose-700 ring-rose-200' }}">{{ ucfirst($check->result) }}</span></td>
                    <td class="px-4 py-2 text-slate-600">{{ $check->lab_report_ref ?: '—' }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $check->checker?->name ?? '—' }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $check->notes ?: '—' }}</td>
                    <td class="px-4 py-2 text-right">@can('manage-all-production')<form method="POST" action="{{ route('admin.productions.quality-checks.destroy', [$production, $check]) }}" onsubmit="return confirm('Remove this check?')">@csrf @method('DELETE')<button type="submit" class="text-xs font-medium text-rose-700">Remove</button></form>@endcan</td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-4 py-6 text-center text-slate-500">No quality checks recorded for this batch.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
