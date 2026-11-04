<x-layouts.admin title="Supplier payments">
    <section class="space-y-6">
        @include('admin.finance._header', ['active' => 'supplier-payments', 'heading' => 'Supplier payments', 'intro' => 'Cash paid to suppliers. Linking a payment to a purchase also settles what is owed on that purchase.'])

        <form method="POST" action="{{ route('admin.finance.supplier-payments.store') }}" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3">
            @csrf
            <h3 class="font-semibold text-slate-800 md:col-span-3">Record a payment</h3>
            <label class="text-sm text-slate-600 md:col-span-2">Purchase being paid (optional)
                <select name="inventory_record_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Not linked to a purchase —</option>
                    @foreach ($unpaid as $record)
                        <option value="{{ $record->id }}" @selected((int) old('inventory_record_id') === $record->id)>#{{ $record->id }} · {{ $record->item_name }} · {{ $record->supplier_name }} · {{ $record->record_date?->format('d M Y') }} · owed {{ number_format((float) $record->total_amount - (float) $record->amount_paid, 0) }} RWF</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-slate-600">Supplier
                <select name="supplier_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— From the purchase —</option>
                    @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected((int) old('supplier_id') === $supplier->id)>{{ $supplier->name }}</option>@endforeach
                </select>
            </label>
            <label class="text-sm text-slate-600">Amount (RWF)<input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Method<select name="method" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach ($methods as $method)<option value="{{ $method }}" @selected(old('method') === $method)>{{ $method }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Paid on<input type="date" name="paid_at" required max="{{ now()->toDateString() }}" value="{{ old('paid_at', now()->toDateString()) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Paid from<select name="cash_account_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">— Not specified —</option>@foreach ($accounts as $account)<option value="{{ $account->id }}" @selected((int) old('cash_account_id') === $account->id)>{{ $account->name }}</option>@endforeach</select></label>
            <label class="text-sm text-slate-600">Reference<input name="reference" maxlength="150" value="{{ old('reference') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Supplier name (if not listed)<input name="supplier_name" maxlength="150" value="{{ old('supplier_name') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600 md:col-span-3">Notes<textarea name="notes" rows="2" maxlength="2000" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes') }}</textarea></label>
            <div><button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Record payment</button></div>
        </form>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-3">Paid on</th><th class="px-4 py-3">Supplier</th><th class="px-4 py-3">Purchase</th><th class="px-4 py-3">Method</th><th class="px-4 py-3">From</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3"></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="px-4 py-3">{{ $payment->paid_at?->format('d M Y') }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $payment->supplier?->name ?? $payment->supplier_name ?? $payment->inventoryRecord?->supplier_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $payment->inventoryRecord ? '#'.$payment->inventoryRecord->id.' '.$payment->inventoryRecord->item_name : '—' }}</td>
                            <td class="px-4 py-3">{{ $payment->method }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $payment->cashAccount?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $payment->reference ?: '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((float) $payment->amount, 2) }}</td>
                            <td class="px-4 py-3 text-right"><form method="POST" action="{{ route('admin.finance.supplier-payments.destroy', $payment) }}" onsubmit="return confirm('Remove this payment? The purchase will show as owed again.')">@csrf @method('DELETE')<button type="submit" class="text-xs font-medium text-rose-700">Remove</button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">No supplier payments recorded.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-admin.table-pagination :paginator="$payments" label="payments" />
    </section>
</x-layouts.admin>
