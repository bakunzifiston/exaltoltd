{{-- Shared by create and edit. Expects $customers, $customerId, $sales, $sale, $lines, $return. --}}
@php
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $existing = $return ? $return->items->keyBy('sale_item_id') : collect();
@endphp
@if ($errors->any())
    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

@unless ($return)
    <form method="GET" action="{{ route('admin.stock-returns.create') }}" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-3">
        <label class="text-sm text-slate-600">1. Supermarket / customer
            <select name="customer_id" onchange="this.form.sale_id && (this.form.sale_id.value=''); this.form.submit()" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Choose…</option>
                @foreach ($customers as $c)<option value="{{ $c->id }}" @selected($customerId === $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </label>
        <label class="text-sm text-slate-600 md:col-span-2">2. Original order / delivery
            <select name="sale_id" onchange="this.form.submit()" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" @disabled(! $customerId)>
                <option value="">{{ $customerId ? ($sales->isEmpty() ? 'No delivered orders for this customer' : 'Choose…') : 'Choose a customer first' }}</option>
                @foreach ($sales as $s)<option value="{{ $s->id }}" @selected($sale?->id === $s->id)>{{ $s->sales_id }}@if ($s->invoice_number) · invoice {{ $s->invoice_number }}@endif · {{ \Illuminate\Support\Carbon::parse($s->sale_date)->format('d M Y') }} · {{ number_format((float) $s->total_revenue, 0) }} RWF</option>@endforeach
            </select>
        </label>
    </form>
@endunless

@if ($sale)
    <form method="POST" action="{{ $return ? route('admin.stock-returns.update', $return) : route('admin.stock-returns.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if ($return) @method('PUT') @endif
        <input type="hidden" name="sale_id" value="{{ $sale->id }}">

        <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-3">
            <div class="md:col-span-3 text-sm text-slate-600">Order <strong>{{ $sale->sales_id }}</strong> for <strong>{{ $sale->customer_name }}</strong>, delivered {{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y') }} · invoice {{ number_format((float) $sale->total_revenue, 0) }} RWF · paid {{ number_format((float) $sale->amount_paid, 0) }} RWF @if ((float) $sale->returned_amount > 0) · already credited {{ number_format((float) $sale->returned_amount, 0) }} RWF @endif · balance {{ number_format($sale->balance_due, 0) }} RWF</div>
            <label class="text-sm text-slate-600">Return date<input type="date" name="return_date" required max="{{ now()->toDateString() }}" value="{{ old('return_date', $return?->return_date?->toDateString() ?? now()->toDateString()) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label class="text-sm text-slate-600">Reason
                <select name="reason" id="returnReason" required class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach (\App\Models\StockReturn::REASONS as $value => $label)<option value="{{ $value }}" @selected(old('reason', $return?->reason) === $value)>{{ $label }}</option>@endforeach</select>
            </label>
            <label class="text-sm text-slate-600">Delivery note no.<input name="delivery_note" maxlength="100" value="{{ old('delivery_note', $return?->delivery_note ?? ($sale->invoice_number ?: $sale->sales_id)) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
            <label id="reasonOtherWrap" class="text-sm text-slate-600 md:col-span-3">Describe the reason<input name="reason_other" maxlength="255" value="{{ old('reason_other', $return?->reason_other) }}" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-800">Products delivered on this order</h3>
                <p class="text-xs text-slate-500">For each product, enter what the customer sold (and is paying for) and what is coming back. Together they cannot exceed what is still outstanding at the customer.</p>
            </div>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-2">Product · batch</th><th class="px-4 py-2 text-right">Delivered</th><th class="px-4 py-2 text-right">On other returns</th><th class="px-4 py-2 text-right">Outstanding</th><th class="px-4 py-2 text-right">Sold &amp; paid</th><th class="px-4 py-2 text-right">Returning</th><th class="px-4 py-2 text-right">Unit price</th><th class="px-4 py-2 text-right">Return value</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($lines as $id => $line)
                        @php($item = $line['item'])
                        <tr class="return-line" data-price="{{ (float) $item->unit_price }}">
                            <td class="px-4 py-2"><input type="hidden" name="lines[{{ $loop->index }}][sale_item_id]" value="{{ $id }}"><span class="font-medium text-slate-900">{{ $item->product?->type ?? 'Product #'.$item->product_id }}</span><span class="block text-xs text-slate-500">Batch {{ $item->production?->batch_id ?? '—' }}</span></td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $qty($line['delivered']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-slate-500">{{ $qty($line['held']) }}</td>
                            <td class="px-4 py-2 text-right font-medium tabular-nums">{{ $qty($line['returnable']) }}</td>
                            <td class="px-4 py-2 text-right"><input type="number" step="0.01" min="0" max="{{ $line['returnable'] }}" name="lines[{{ $loop->index }}][quantity_sold_reported]" value="{{ old('lines.'.$loop->index.'.quantity_sold_reported', $existing[$id]->quantity_sold_reported ?? '') }}" class="w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm" @disabled($line['returnable'] <= 0)></td>
                            <td class="px-4 py-2 text-right"><input type="number" step="0.01" min="0" max="{{ $line['returnable'] }}" name="lines[{{ $loop->index }}][quantity_returned]" value="{{ old('lines.'.$loop->index.'.quantity_returned', $existing[$id]->quantity_returned ?? '') }}" class="returned-input w-24 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm" @disabled($line['returnable'] <= 0)></td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $item->unit_price, 0) }}</td>
                            <td class="line-value px-4 py-2 text-right font-medium tabular-nums">0</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="border-t border-slate-200 bg-slate-50 font-semibold"><td colspan="7" class="px-4 py-2 text-right">Total value returned</td><td id="returnTotal" class="px-4 py-2 text-right tabular-nums">0 RWF</td></tr></tfoot>
            </table>
        </div>

        <div class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-2">
            <label class="text-sm text-slate-600">Notes<textarea name="notes" rows="3" maxlength="2000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('notes', $return?->notes) }}</textarea></label>
            <label class="text-sm text-slate-600">Supporting documents <span class="text-xs text-slate-400">(delivery note, photos; PDF or image, up to 5)</span><input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" class="mt-2 block text-sm"></label>
        </div>

        <div class="flex flex-wrap gap-3">
            <button type="submit" name="action" value="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Submit for inspection</button>
            <button type="submit" name="action" value="draft" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Save as draft</button>
            <a href="{{ route('admin.stock-returns.index') }}" class="px-2 py-2 text-sm text-slate-500">Cancel</a>
        </div>
    </form>

    <script>
    (() => {
        const format = (n) => Math.round(n).toLocaleString('en-US');
        const recalc = () => {
            let total = 0;
            document.querySelectorAll('.return-line').forEach((row) => {
                const qty = parseFloat(row.querySelector('.returned-input')?.value || 0);
                const value = qty * parseFloat(row.dataset.price || 0);
                row.querySelector('.line-value').textContent = format(value);
                total += value;
            });
            document.getElementById('returnTotal').textContent = format(total) + ' RWF';
        };
        document.querySelectorAll('.returned-input').forEach((input) => input.addEventListener('input', recalc));
        recalc();
        const reason = document.getElementById('returnReason');
        const other = document.getElementById('reasonOtherWrap');
        const toggle = () => { other.style.display = reason.value === 'other' ? '' : 'none'; };
        reason.addEventListener('change', toggle);
        toggle();
    })();
    </script>
@endif
