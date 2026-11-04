<x-layouts.admin title="Loans">
    <section class="space-y-6">
        @include('admin.finance._header', ['active' => 'loans', 'heading' => 'Loans', 'intro' => 'Borrowings and repayments. Interest goes to the income statement; principal reduces the loan and is a financing outflow.'])

        <form method="POST" action="{{ route('admin.finance.loans.store') }}" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3">
            @csrf
            <h3 class="font-semibold text-slate-800 md:col-span-3">Record a loan</h3>
            <label class="text-sm text-slate-600">Lender<input name="lender" required maxlength="150" value="{{ old('lender') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Reference<input name="reference" maxlength="150" value="{{ old('reference') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Principal (RWF)<input type="number" step="0.01" min="0.01" name="principal" required value="{{ old('principal') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Interest rate (%)<input type="number" step="0.0001" min="0" name="interest_rate" required value="{{ old('interest_rate', 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Rate basis<select name="rate_basis" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach (['annual' => 'Per year', 'monthly' => 'Per month', 'flat' => 'Flat'] as $value => $label)<option value="{{ $value }}" @selected(old('rate_basis') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Start date<input type="date" name="start_date" required value="{{ old('start_date') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Term (months)<input type="number" min="1" max="600" name="term_months" required value="{{ old('term_months', 12) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Repayments<select name="repayment_frequency" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach (['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual', 'irregular' => 'Irregular'] as $value => $label)<option value="{{ $value }}" @selected(old('repayment_frequency') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Status<select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Received into<select name="cash_account_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">—</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((int) old('cash_account_id') === $account->id)>{{ $account->name }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600 md:col-span-2">Notes<input name="notes" maxlength="2000" value="{{ old('notes') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <div><button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Record loan</button></div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-3">Lender</th><th class="px-4 py-3">Start</th><th class="px-4 py-3 text-right">Principal</th><th class="px-4 py-3">Rate</th><th class="px-4 py-3 text-right">Outstanding ({{ $asOf->format('M Y') }})</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Record a repayment</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($loans as $loan)
                        <tr class="align-top">
                            <td class="px-4 py-3"><span class="font-medium text-slate-900">{{ $loan->lender }}</span>@if ($loan->reference)<span class="block text-xs text-slate-500">{{ $loan->reference }}</span>@endif<span class="block text-xs text-slate-400">{{ $loan->repayments_count }} repayments · {{ $loan->term_months }} months</span></td>
                            <td class="px-4 py-3">{{ $loan->start_date?->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $loan->principal, 2) }}</td>
                            <td class="px-4 py-3">{{ rtrim(rtrim(number_format((float) $loan->interest_rate, 4), '0'), '.') }}% {{ $loan->rate_basis }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($loan->outstandingAt($asOf), 2) }}</td>
                            <td class="px-4 py-3">{{ $statuses[$loan->status] ?? $loan->status }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.finance.loans.repayments.store', $loan) }}" class="grid gap-2 sm:grid-cols-2">
                                    @csrf
                                    <input type="date" name="paid_at" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <input type="number" step="0.01" min="0.01" name="total_amount" required placeholder="Total paid" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <input type="number" step="0.01" min="0" name="principal_portion" required placeholder="Principal" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <input type="number" step="0.01" min="0" name="interest_portion" required placeholder="Interest" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <select name="cash_account_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"><option value="">Paid from…</option>@foreach ($accounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select>
                                    <input name="reference" maxlength="150" placeholder="Reference" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                    <button type="submit" class="rounded-lg bg-teal-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-800 sm:col-span-2">Add repayment</button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-right"><form method="POST" action="{{ route('admin.finance.loans.destroy', $loan) }}" onsubmit="return confirm('Remove this loan and its repayments?')">@csrf @method('DELETE')<button type="submit" class="text-xs font-medium text-rose-700">Remove</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">No loans recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$loans" label="loans" />

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800">Recent repayments</h3>
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-3">Paid on</th><th class="px-4 py-3">Loan</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3 text-right">Principal</th><th class="px-4 py-3 text-right">Interest</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($repayments as $repayment)
                        <tr>
                            <td class="px-4 py-3">{{ $repayment->paid_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3">{{ $repayment->loan?->lender ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $repayment->total_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $repayment->principal_portion, 2) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $repayment->interest_portion, 2) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $repayment->reference ?: '—' }}</td>
                            <td class="px-4 py-3 text-right"><form method="POST" action="{{ route('admin.finance.loans.repayments.destroy', $repayment) }}" onsubmit="return confirm('Remove this repayment?')">@csrf @method('DELETE')<button type="submit" class="text-xs font-medium text-rose-700">Remove</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-500">No repayments recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.admin>
