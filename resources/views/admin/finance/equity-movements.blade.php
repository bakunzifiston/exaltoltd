<x-layouts.admin title="Equity">
    <section class="space-y-6">
        @include('admin.finance._header', ['active' => 'equity', 'heading' => 'Equity', 'intro' => 'Capital put into the business and drawings taken out by the owners.'])

        <form method="POST" action="{{ route('admin.finance.equity.store') }}" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3">
            @csrf
            <h3 class="font-semibold text-slate-800 md:col-span-3">Record a movement</h3>
            <label class="text-sm text-slate-600">Type<select name="type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Amount (RWF)<input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Date<input type="date" name="occurred_at" required max="{{ now()->toDateString() }}" value="{{ old('occurred_at', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Cash account<select name="cash_account_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">—</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((int) old('cash_account_id') === $account->id)>{{ $account->name }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600 md:col-span-2">Description<input name="description" maxlength="255" value="{{ old('description') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <div><button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Record</button></div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Type</th><th class="px-4 py-3">Description</th><th class="px-4 py-3">Account</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="px-4 py-3">{{ $movement->occurred_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3">{{ $types[$movement->type] ?? $movement->type }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $movement->description ?: '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $movement->cashAccount?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums {{ in_array($movement->type, \App\Models\EquityMovement::OUTFLOW_TYPES, true) ? 'text-rose-700' : '' }}">{{ in_array($movement->type, \App\Models\EquityMovement::OUTFLOW_TYPES, true) ? '(' . number_format((float) $movement->amount, 2) . ')' : number_format((float) $movement->amount, 2) }}</td>
                            <td class="px-4 py-3 text-right"><form method="POST" action="{{ route('admin.finance.equity.destroy', $movement) }}" onsubmit="return confirm('Remove this movement?')">@csrf @method('DELETE')<button type="submit" class="text-xs font-medium text-rose-700">Remove</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No equity movements recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$movements" label="movements" />
    </section>
</x-layouts.admin>
