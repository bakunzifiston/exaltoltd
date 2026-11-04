@php
    $fact = fn ($label, $value) => ['label' => $label, 'value' => $value === null || $value === '' ? '—' : $value];
    $facts = [
        $fact('Product', $production->product?->type),
        $fact('Produced', number_format($production->quantity_produced, 2).' units'),
        $fact('Damaged', number_format($production->damaged, 2)),
        $fact('Remaining in stock', number_format($production->quantity_remaining, 2)),
        $fact('Production date', \Illuminate\Support\Carbon::parse($production->production_date)->format('d M Y')),
        $fact('Packaging date', $production->packaging_date?->format('d M Y')),
        $fact('Best before', $production->best_before_date?->format('d M Y')),
        $fact('Use by / expiry', $production->expiry_date?->format('d M Y')),
        $fact('Line / shift', trim(($production->production_line ?: '—').' / '.($production->shift ?: '—'))),
        $fact('Started – finished', $production->started_at ? $production->started_at->format('d M H:i').' – '.($production->finished_at?->format('H:i') ?? '…') : null),
        $fact('Recipe version', $production->recipe_version),
        $fact('Stored in', $production->storage_location),
        $fact('Responsible worker', $production->responsible_staff),
        $fact('Barcode', $production->barcode),
    ];
@endphp
<div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <dl class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ($facts as $f)
            <div><dt class="text-sm text-slate-500">{{ $f['label'] }}</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $f['value'] }}</dd></div>
        @endforeach
    </dl>
    @if ($production->quality_control_notes)
        <div class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-700"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Quality control notes</p><p class="mt-1 whitespace-pre-line">{{ $production->quality_control_notes }}</p></div>
    @endif

    <div class="mt-6">
        <h3 class="text-sm font-semibold text-slate-800">Raw materials consumed</h3>
        <p class="text-xs text-slate-400">Each material links to its delivery (lot), where you can see its full usage history and trace it forward.</p>
        <table class="mt-2 min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50"><tr><th class="px-3 py-1.5 text-left">Material (lot)</th><th class="px-3 py-1.5 text-right">Qty used</th><th class="px-3 py-1.5 text-right">Unit cost</th><th class="px-3 py-1.5 text-right">Line cost</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($materials as $material)
                    <tr>
                        <td class="px-3 py-1.5">
                            @if ($material['record'])
                                @can('manage-catalog')<a href="{{ route('admin.inventory-records.show', $material['record']) }}" class="text-teal-700 hover:underline">{{ $material['name'] }}</a>@else{{ $material['name'] }}@endcan
                                <span class="text-xs text-slate-400">· {{ $material['record']->record_date?->format('Y-m-d') }}@if ($material['record']->invoice_number) · Inv. {{ $material['record']->invoice_number }}@endif</span>
                            @else
                                {{ $material['name'] }}
                            @endif
                        </td>
                        <td class="px-3 py-1.5 text-right">{{ number_format($material['quantity'], 2) }}</td>
                        <td class="px-3 py-1.5 text-right text-slate-600">{{ $material['unit_cost'] !== null ? number_format($material['unit_cost'], 0).' RWF' : '—' }}</td>
                        <td class="px-3 py-1.5 text-right font-medium">{{ $material['line_cost'] !== null ? number_format($material['line_cost'], 0).' RWF' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-4 text-center text-slate-500">No raw materials recorded for this batch.</td></tr>
                @endforelse
            </tbody>
            @if ($materialsCost > 0)
                <tfoot><tr class="border-t border-slate-200 font-semibold"><td colspan="3" class="px-3 py-2 text-right">Material cost of this batch</td><td class="px-3 py-2 text-right">{{ number_format($materialsCost, 0) }} RWF</td></tr></tfoot>
            @endif
        </table>
    </div>
</div>

@can('view-analytics')
<div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-800">Sales from this batch</h3>
            <p class="text-xs text-slate-400">Every sale that drew finished goods from {{ $production->batch_id }}.</p>
        </div>
        @unless ($summary['reconciles'])
            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-200">Ledger mismatch: {{ number_format($summary['left_batch'], 2) }} left the batch, but sales, returns and samples account for {{ number_format($summary['expected_left'], 2) }}</span>
        @endunless
    </div>
    <table class="mt-3 min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50"><tr><th class="px-3 py-1.5 text-left">Sale</th><th class="px-3 py-1.5 text-left">Date</th><th class="px-3 py-1.5 text-left">Customer</th><th class="px-3 py-1.5 text-right">Qty</th><th class="px-3 py-1.5 text-right">Unit price</th><th class="px-3 py-1.5 text-right">Line total</th><th class="px-3 py-1.5 text-left">Payment</th><th class="px-3 py-1.5 text-left">Recorded by</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($forward->where('kind', 'sale') as $row)
                <tr>
                    <td class="px-3 py-1.5">@can('record-sales')<a href="{{ route('admin.sales.show', $row['sale']) }}" class="text-teal-700 hover:underline">{{ $row['sales_id'] }}</a>@else{{ $row['sales_id'] }}@endcan</td>
                    <td class="px-3 py-1.5 text-slate-600">{{ $row['sale_date'] ?? '—' }}</td>
                    <td class="px-3 py-1.5">{{ $row['customer'] ?: '—' }}</td>
                    <td class="px-3 py-1.5 text-right">{{ number_format($row['quantity'], 2) }}</td>
                    <td class="px-3 py-1.5 text-right text-slate-600">{{ number_format($row['unit_price'], 0) }} RWF</td>
                    <td class="px-3 py-1.5 text-right font-medium">{{ number_format($row['line_total'], 0) }} RWF</td>
                    <td class="px-3 py-1.5"><x-admin.sale-status-badge :status="$row['payment_status']" type="payment" /></td>
                    <td class="px-3 py-1.5 text-slate-600">{{ $row['recorded_by'] ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-3 py-4 text-center text-slate-500">No sales recorded from this batch yet.</td></tr>
            @endforelse
        </tbody>
        @if ($forward->where('kind', 'sale')->isNotEmpty())
            <tfoot><tr class="border-t border-slate-200 font-semibold"><td colspan="3" class="px-3 py-2 text-right">Total sold from this batch</td><td class="px-3 py-2 text-right">{{ number_format($summary['sold_all'], 2) }}</td><td></td><td class="px-3 py-2 text-right">{{ number_format($forward->where('kind', 'sale')->sum('line_total'), 0) }} RWF</td><td colspan="2"></td></tr></tfoot>
        @endif
    </table>
</div>
@endcan
