@php($qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.'))
<div class="grid grid-cols-2 gap-3 md:grid-cols-4">
    @foreach (['Produced' => $summary['produced'], 'Damaged' => $summary['damaged'], 'Sold (net)' => $summary['sold'], 'Delivered' => $summary['delivered'], 'Returned' => $summary['returned'], 'Samples' => $summary['samples'], 'In stock' => $summary['remaining'], 'Customers' => $summary['customers']] as $label => $value)
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-1 text-xl font-semibold tabular-nums text-slate-900">{{ $qty($value) }}</p></div>
    @endforeach
</div>
@unless ($summary['reconciles'])
    <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Stock ledger and sales disagree: {{ $qty($summary['left_batch']) }} units left the batch but sales, returns and samples account for {{ $qty($summary['expected_left']) }}. Check for sales or adjustments recorded outside the system before relying on this trace.</p>
@endunless

<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-4 py-3">
        <h3 class="text-sm font-semibold text-slate-800">Ingredients — where this batch came from</h3>
        <p class="text-xs text-slate-400">Backward trace: each raw-material delivery used, its supplier and supplier lot, and the other batches made from the same delivery.</p>
    </div>
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-2">Material</th><th class="px-4 py-2">Supplier · supplier lot</th><th class="px-4 py-2">Received</th><th class="px-4 py-2">Material expiry</th><th class="px-4 py-2">Inspection</th><th class="px-4 py-2 text-right">Qty used</th><th class="px-4 py-2">Also used in</th><th class="px-4 py-2"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($backward as $row)
                @php($lot = $row['lot'])
                <tr class="align-top">
                    <td class="px-4 py-2"><span class="font-medium text-slate-900">{{ $lot?->item_name ?? 'Unknown lot #'.$row['inventory_id'] }}</span>@if ($lot?->lot_number)<span class="block text-xs text-slate-500">Our lot {{ $lot->lot_number }}</span>@endif @if ($lot?->trashed())<span class="block text-xs text-rose-600">Record deleted</span>@endif</td>
                    <td class="px-4 py-2">{{ $lot?->supplier?->name ?? $lot?->supplier_name ?? '—' }}<span class="block text-xs text-slate-500">{{ $lot?->supplier_lot_number ? 'Lot '.$lot->supplier_lot_number : 'No supplier lot recorded' }}</span></td>
                    <td class="px-4 py-2">{{ $lot?->record_date?->format('d M Y') ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $lot?->expiry_date?->format('d M Y') ?? '—' }}</td>
                    <td class="px-4 py-2">{{ \App\Models\InventoryRecord::INSPECTION_STATUSES[$lot?->inspection_status] ?? '—' }}@if ($lot?->coa_reference)<span class="block text-xs text-slate-500">CoA {{ $lot->coa_reference }}</span>@endif</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $qty($row['quantity']) }}</td>
                    <td class="px-4 py-2 text-xs">
                        @forelse ($row['other_batches'] as $other)
                            @can('manage-all-production')<a href="{{ route('admin.productions.show', ['production' => $other['production_id'], 'tab' => 'traceability']) }}" class="text-teal-700 hover:underline">{{ $other['batch_id'] }}</a>@else{{ $other['batch_id'] }}@endcan{{ $loop->last ? '' : ', ' }}
                        @empty
                            <span class="text-slate-400">No other batch</span>
                        @endforelse
                    </td>
                    <td class="px-4 py-2 text-right">@if ($lot && ! $lot->trashed())@can('manage-catalog')<a href="{{ route('admin.inventory-records.recall', $lot) }}" class="whitespace-nowrap text-xs font-medium text-teal-700 hover:underline">Trace forward</a>@endcan @endif</td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-6 text-center text-slate-500">No raw materials are recorded for this batch, so it cannot be traced back to a supplier.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-4 py-3">
        <h3 class="text-sm font-semibold text-slate-800">Distribution — where this batch went</h3>
        <p class="text-xs text-slate-400">Forward trace: every customer who received units from this batch. This is the contact list for a recall.</p>
    </div>
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-2">Sale</th><th class="px-4 py-2">Date</th><th class="px-4 py-2">Customer</th>@can('view-analytics')<th class="px-4 py-2">Contact · delivery address</th>@endcan<th class="px-4 py-2">Delivery</th><th class="px-4 py-2 text-right">Qty</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($forward as $row)
                <tr class="align-top">
                    <td class="px-4 py-2">
                        @if ($row['kind'] === 'sample')
                            <a href="{{ route('admin.production-samples.show', $row['sample']) }}" class="text-teal-700 hover:underline">{{ $row['sales_id'] }}</a> <span class="text-xs text-slate-500">sample</span>
                        @else
                            @can('record-sales')<a href="{{ route('admin.sales.show', $row['sale']) }}" class="text-teal-700 hover:underline">{{ $row['sales_id'] }}</a>@else{{ $row['sales_id'] }}@endcan
                        @endif
                    </td>
                    <td class="px-4 py-2">{{ $row['sale_date'] ?? '—' }}</td>
                    <td class="px-4 py-2 font-medium text-slate-900">@if ($row['customer_id'])@can('manage-catalog')<a href="{{ route('admin.customers.show', $row['customer_id']) }}" class="hover:underline">{{ $row['customer'] }}</a>@else{{ $row['customer'] }}@endcan @else{{ $row['customer'] ?: '—' }}@endif</td>
                    @can('view-analytics')<td class="px-4 py-2 text-slate-600">{{ $row['phone'] ?: '—' }}@if ($row['address'])<span class="block text-xs text-slate-500">{{ $row['address'] }}</span>@endif</td>@endcan
                    <td class="px-4 py-2">@if ($row['kind'] === 'sample')<span class="rounded-full bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 ring-1 ring-sky-200">Free sample</span>@else<x-admin.sale-status-badge :status="$row['delivery_status']" type="delivery" />@endif @if ($row['delivered_at'])<span class="block text-xs text-slate-500">{{ \Illuminate\Support\Carbon::parse($row['delivered_at'])->format('d M Y') }}</span>@endif</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $qty($row['quantity']) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">Nothing from this batch has been sold yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-4 py-3">
        <h3 class="text-sm font-semibold text-slate-800">Movement history</h3>
        <p class="text-xs text-slate-400">Raw materials drawn, finished goods produced, sold, returned and adjusted, samples, quality checks and release decisions. Latest first{{ $timeline->count() > 25 ? '; showing the latest 25 of '.$timeline->count() : '' }}.</p>
    </div>
    @php($recent = $timeline->reverse()->values())
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-slate-600"><tr><th class="px-4 py-2">When</th><th class="px-4 py-2">Stock</th><th class="px-4 py-2">Event</th><th class="px-4 py-2 text-right">Quantity</th><th class="px-4 py-2">Detail</th><th class="px-4 py-2">By</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($recent as $event)
                <tr class="{{ $loop->index >= 25 ? 'older-movement hidden' : '' }}">
                    <td class="whitespace-nowrap px-4 py-2 text-slate-600">{{ $event['at']?->format('d M Y H:i') ?? '—' }}</td>
                    <td class="px-4 py-2">{{ $event['kind'] }}</td>
                    <td class="px-4 py-2">{{ $event['event'] }}</td>
                    <td class="px-4 py-2 text-right tabular-nums">{{ $event['quantity'] === null ? '' : $qty($event['quantity']) }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $event['detail'] ?: '—' }}</td>
                    <td class="px-4 py-2 text-slate-600">{{ $event['user'] ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500">No movements recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($recent->count() > 25)
        <button type="button" onclick="document.querySelectorAll('.older-movement').forEach(r => r.classList.toggle('hidden')); this.textContent = this.textContent.startsWith('Show') ? 'Show only the latest 25' : 'Show all {{ $recent->count() }} movements'" class="m-3 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Show all {{ $recent->count() }} movements</button>
    @endif
</div>
