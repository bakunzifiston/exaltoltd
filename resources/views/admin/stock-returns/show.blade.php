<x-layouts.admin title="Return {{ $return->reference }}">
    @php
        $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $money = fn ($v) => number_format((float) $v, 0).' RWF';
        $approved = $return->status === \App\Models\StockReturn::APPROVED;
        $pending = $return->status === \App\Models\StockReturn::PENDING_INSPECTION;
        $paid = (float) $sale->payments->sum('amount');
        // Before approval, show what the order will look like once this return is credited.
        $creditedBefore = (float) $sale->returned_amount - ($approved ? (float) $return->total_value : 0);
        $netAfter = (float) $sale->total_revenue - $creditedBefore - (in_array($return->status, ['approved', 'pending_inspection', 'draft'], true) ? (float) $return->total_value : 0);
        $soldValue = $return->items->sum(fn ($i) => (float) $i->quantity_sold_reported * (float) $i->unit_price);
        $defaultSplit = fn ($item) => match ($return->reason) { 'expired' => 'quantity_expired', 'damaged' => 'quantity_damaged', default => 'quantity_restocked' };
    @endphp
    <section class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('admin.stock-returns.index') }}" class="text-sm text-teal-700">Stock returns</a>
                <h2 class="flex flex-wrap items-center gap-3 text-2xl font-semibold text-slate-900">Return {{ $return->reference }} @include('admin.partials._status-badge', ['status' => $return->status, 'label' => $return->statusLabel()])</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $return->customer_name }} · returned {{ $return->return_date->format('d M Y') }} · {{ $return->reasonLabel() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($return->isDraft() && ($return->created_by === auth()->id() || auth()->user()->can('manage-all-sales')))
                    <a href="{{ route('admin.stock-returns.edit', $return) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Edit draft</a>
                    <form method="POST" action="{{ route('admin.stock-returns.submit', $return) }}">@csrf<button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Submit for inspection</button></form>
                @endif
            </div>
        </div>
        @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"><ul class="list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                <dl class="grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
                    <div><dt class="text-slate-500">Original order</dt><dd class="mt-1 font-medium">@can('record-sales')<a href="{{ route('admin.sales.show', $sale) }}" class="text-teal-700 hover:underline">{{ $sale->sales_id }}</a>@else{{ $sale->sales_id }}@endcan</dd></div>
                    <div><dt class="text-slate-500">Delivery note</dt><dd class="mt-1 font-medium">{{ $return->delivery_note ?: '—' }}</dd></div>
                    <div><dt class="text-slate-500">Delivered on</dt><dd class="mt-1 font-medium">{{ \Illuminate\Support\Carbon::parse($sale->sale_date)->format('d M Y') }}</dd></div>
                    <div><dt class="text-slate-500">Recorded by</dt><dd class="mt-1 font-medium">{{ $return->creator?->name ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Decided by</dt><dd class="mt-1 font-medium">{{ $return->decider?->name ?? '—' }}@if ($return->decided_at)<span class="block text-xs text-slate-500">{{ $return->decided_at->format('d M Y H:i') }}</span>@endif</dd></div>
                    <div><dt class="text-slate-500">Value returned</dt><dd class="mt-1 font-semibold">{{ $money($return->total_value) }}</dd></div>
                </dl>
                @if ($return->notes)<p class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">{{ $return->notes }}</p>@endif
                @if ($return->inspection_notes)<p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700"><span class="font-medium">{{ $approved ? 'Inspection' : 'Decision' }} notes:</span> {{ $return->inspection_notes }}</p>@endif
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-800">Order reconciliation</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-600">Invoiced on the order</dt><dd class="tabular-nums">{{ $money($sale->total_revenue) }}</dd></div>
                    @if ($creditedBefore > 0)<div class="flex justify-between"><dt class="text-slate-600">Credited for earlier returns</dt><dd class="tabular-nums">−{{ $money($creditedBefore) }}</dd></div>@endif
                    <div class="flex justify-between"><dt class="text-slate-600">{{ $approved ? 'Credited' : 'To be credited' }} for this return</dt><dd class="tabular-nums">−{{ $money($return->total_value) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 font-medium"><dt>Customer is charged for</dt><dd class="tabular-nums">{{ $money($netAfter) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-600">Payments received</dt><dd class="tabular-nums">{{ $paid > 0 ? '−' : '' }}{{ $money($paid) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold"><dt>{{ $netAfter - $paid >= 0 ? 'Balance still owed' : 'Credit owed to customer' }}</dt><dd class="tabular-nums {{ $netAfter - $paid > 0.009 ? 'text-rose-700' : 'text-emerald-700' }}">{{ $money(abs($netAfter - $paid)) }}</dd></div>
                    <div class="flex justify-between text-xs text-slate-500"><dt>Customer reported sold on these lines</dt><dd class="tabular-nums">{{ $money($soldValue) }}</dd></div>
                </dl>
                @if ($netAfter - $paid < -0.009)<p class="mt-3 text-xs text-amber-700">The customer has paid more than the goods they kept. Arrange a refund or carry the credit to their next order.</p>@endif
                @if ($netAfter - $paid > 0.009 && $approved)
                    @can('record-sales')<a href="{{ route('admin.sales.show', $sale) }}" class="mt-3 inline-block text-xs font-medium text-teal-700 hover:underline">Record the payment on the order</a>@endcan
                @endif
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <h3 class="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-800">Returned products</h3>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-2">Product · batch</th><th class="px-4 py-2 text-right">Delivered</th><th class="px-4 py-2 text-right">Sold &amp; paid</th><th class="px-4 py-2 text-right">Returned</th>@if ($approved)<th class="px-4 py-2 text-right">Back in stock</th><th class="px-4 py-2 text-right">Damaged</th><th class="px-4 py-2 text-right">Expired</th><th class="px-4 py-2 text-right">Rejected</th>@endif<th class="px-4 py-2 text-right">Unit price</th><th class="px-4 py-2 text-right">Value</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($return->items as $item)
                        <tr>
                            <td class="px-4 py-2"><span class="font-medium text-slate-900">{{ $item->product?->type }}</span><span class="block text-xs text-slate-500">Batch @if ($item->production)<a href="{{ route('admin.productions.show', ['production' => $item->production, 'tab' => 'traceability']) }}" class="text-teal-700 hover:underline">{{ $item->production->batch_id }}</a>@else — @endif</span>@if ($item->condition_notes)<span class="block text-xs text-slate-500">{{ $item->condition_notes }}</span>@endif</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $qty($item->quantity_delivered) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $qty($item->quantity_sold_reported) }}</td>
                            <td class="px-4 py-2 text-right font-medium tabular-nums">{{ $qty($item->quantity_returned) }}</td>
                            @if ($approved)
                                <td class="px-4 py-2 text-right tabular-nums text-emerald-700">{{ $qty($item->quantity_restocked) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $qty($item->quantity_damaged) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $qty($item->quantity_expired) }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ $qty($item->quantity_rejected) }}</td>
                            @endif
                            <td class="px-4 py-2 text-right tabular-nums">{{ number_format((float) $item->unit_price, 0) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $money($item->line_value) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($pending)
            @can('approve-returns')
                <form method="POST" action="{{ route('admin.stock-returns.approve', $return) }}" class="space-y-4 rounded-xl border border-teal-200 bg-white p-5 shadow-sm">
                    @csrf
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800">Inspect and approve</h3>
                        <p class="text-xs text-slate-500">Split each returned quantity by what you found. Only units marked sellable go back into stock; damaged, expired and rejected units are recorded and written off. Approving credits the order with the full return value.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-3 py-2">Product</th><th class="px-3 py-2 text-right">Returned</th><th class="px-3 py-2 text-right">Sellable</th><th class="px-3 py-2 text-right">Damaged</th><th class="px-3 py-2 text-right">Expired</th><th class="px-3 py-2 text-right">Rejected</th><th class="px-3 py-2">Condition notes</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($return->items as $item)
                                    <tr>
                                        <td class="px-3 py-2">{{ $item->product?->type }} <span class="text-xs text-slate-500">{{ $item->production?->batch_id }}</span></td>
                                        <td class="px-3 py-2 text-right font-medium tabular-nums">{{ $qty($item->quantity_returned) }}</td>
                                        @foreach (['quantity_restocked', 'quantity_damaged', 'quantity_expired', 'quantity_rejected'] as $field)
                                            <td class="px-3 py-2 text-right"><input type="number" step="0.01" min="0" max="{{ (float) $item->quantity_returned }}" name="inspection[{{ $item->id }}][{{ $field }}]" value="{{ old('inspection.'.$item->id.'.'.$field, $defaultSplit($item) === $field ? (float) $item->quantity_returned : 0) }}" class="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-right text-sm"></td>
                                        @endforeach
                                        <td class="px-3 py-2"><input name="inspection[{{ $item->id }}][condition_notes]" maxlength="255" value="{{ old('inspection.'.$item->id.'.condition_notes') }}" class="w-56 rounded-lg border border-slate-300 px-2 py-1.5 text-sm"></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <label class="block text-sm text-slate-600">Inspection notes<textarea name="inspection_notes" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('inspection_notes') }}</textarea></label>
                    <button type="submit" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800" onclick="return confirm('Approve this return? Sellable units go back into stock and the order is credited {{ $money($return->total_value) }}.')">Approve return</button>
                </form>
                <form method="POST" action="{{ route('admin.stock-returns.reject', $return) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-rose-200 bg-white p-5 shadow-sm">
                    @csrf
                    <label class="flex-1 text-sm text-slate-600">Reject the return — reason <span class="text-rose-600">*</span><input name="inspection_notes" required minlength="5" maxlength="2000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                    <button type="submit" class="rounded-lg border border-rose-300 bg-white px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50">Reject</button>
                </form>
            @else
                <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Waiting for a manager to inspect and approve. Nothing has been added to stock or credited yet.</p>
            @endcan
        @endif

        @if (in_array($return->status, ['draft', 'pending_inspection'], true) && ($return->created_by === auth()->id() || auth()->user()->can('approve-returns')))
            <form method="POST" action="{{ route('admin.stock-returns.cancel', $return) }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <label class="flex-1 text-sm text-slate-600">Cancel this return — reason<input name="reason" required minlength="5" maxlength="2000" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel return</button>
            </form>
        @endif

        @if ($otherReturns->isNotEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-800">Other returns on this order</h3>
                <ul class="mt-2 space-y-1 text-sm">@foreach ($otherReturns as $other)<li><a href="{{ route('admin.stock-returns.show', $other) }}" class="text-teal-700 hover:underline">{{ $other->reference }}</a> · {{ $other->return_date->format('d M Y') }} · {{ $money($other->total_value) }} · {{ $other->statusLabel() }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            @include('admin.partials._documents', ['record' => $return, 'routePrefix' => 'admin.stock-returns', 'param' => 'stockReturn', 'canRemove' => $return->isDraft()])
            @include('admin.partials._audit-trail', ['record' => $return])
        </div>
    </section>
</x-layouts.admin>
