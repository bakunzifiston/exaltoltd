<x-layouts.admin title="Fixed assets">
    <section class="space-y-6">
        @include('admin.finance._header', ['active' => 'fixed-assets', 'heading' => 'Fixed assets', 'intro' => 'Machinery and equipment. Each asset is depreciated straight-line from its acquisition month; the charge feeds every statement.'])

        <form method="POST" action="{{ route('admin.finance.fixed-assets.store') }}" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3">
            @csrf
            <h3 class="font-semibold text-slate-800 md:col-span-3">Register an asset</h3>
            <label class="text-sm text-slate-600">Name<input name="name" required maxlength="150" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Category<input name="category" maxlength="100" value="{{ old('category') }}" placeholder="e.g. Machinery" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Acquired on<input type="date" name="acquisition_date" required value="{{ old('acquisition_date') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Cost (RWF)<input type="number" step="0.01" min="0" name="cost" required value="{{ old('cost') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Salvage value (RWF)<input type="number" step="0.01" min="0" name="salvage_value" required value="{{ old('salvage_value', 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Useful life (months)<input type="number" min="1" max="1200" name="useful_life_months" required value="{{ old('useful_life_months', 60) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Supplier<select name="supplier_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">—</option>@foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((int) old('supplier_id') === $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Paid from<select name="cash_account_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">—</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((int) old('cash_account_id') === $account->id)>{{ $account->name }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Disposed on (optional)<input type="date" name="disposal_date" value="{{ old('disposal_date') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Disposal proceeds (RWF)<input type="number" step="0.01" min="0" name="disposal_proceeds" value="{{ old('disposal_proceeds') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600 md:col-span-2">Notes<input name="notes" maxlength="2000" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <div><button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Register asset</button></div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table data-bulk="{{ route('admin.bulk-delete', 'fixed-assets') }}" class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-3">Asset</th><th class="px-4 py-3">Acquired</th><th class="px-4 py-3 text-right">Cost</th><th class="px-4 py-3 text-right">Monthly charge</th><th class="px-4 py-3 text-right">Accumulated ({{ $asOf->format('M Y') }})</th><th class="px-4 py-3 text-right">Net book value</th><th class="px-4 py-3">Disposed</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($assets as $asset)
                        <tr>
                            <td class="px-4 py-3"><span class="font-medium text-slate-900">{{ $asset->name }}</span>@if ($asset->category)<span class="block text-xs text-slate-500">{{ $asset->category }}</span>@endif</td>
                            <td class="px-4 py-3">{{ $asset->acquisition_date?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $asset->cost, 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($asset->monthlyCharge(), 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($asset->accumulatedDepreciationAt($asOf), 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($asset->netBookValueAt($asOf), 2) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $asset->disposal_date?->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <details class="inline-block text-left">
                                    <summary class="cursor-pointer text-xs font-medium text-teal-700">Edit</summary>
                                    <form method="POST" action="{{ route('admin.finance.fixed-assets.update', $asset) }}" class="mt-2 grid w-72 gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                                        @csrf @method('PUT')
                                        <input name="name" required maxlength="150" value="{{ $asset->name }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <input name="category" maxlength="100" value="{{ $asset->category }}" placeholder="Category" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <input type="date" name="acquisition_date" required value="{{ $asset->acquisition_date?->toDateString() }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <input type="number" step="0.01" min="0" name="cost" required value="{{ $asset->cost }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <input type="number" step="0.01" min="0" name="salvage_value" required value="{{ $asset->salvage_value }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <input type="number" min="1" max="1200" name="useful_life_months" required value="{{ $asset->useful_life_months }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <input type="date" name="disposal_date" value="{{ $asset->disposal_date?->toDateString() }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <input type="number" step="0.01" min="0" name="disposal_proceeds" value="{{ $asset->disposal_proceeds }}" placeholder="Disposal proceeds" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <input type="hidden" name="supplier_id" value="{{ $asset->supplier_id }}"><input type="hidden" name="cash_account_id" value="{{ $asset->cash_account_id }}">
                                        <input name="notes" maxlength="2000" value="{{ $asset->notes }}" placeholder="Notes" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                        <button type="submit" class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-800">Save</button>
                                    </form>
                                </details>
                                <form method="POST" action="{{ route('admin.finance.fixed-assets.destroy', $asset) }}" class="inline" onsubmit="return confirm('Remove this asset from the register?')">@csrf @method('DELETE')<button type="submit" class="ml-2 text-xs font-medium text-rose-700">Remove</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">No fixed assets registered, so no depreciation is charged.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$assets" label="assets" />
    </section>
</x-layouts.admin>
