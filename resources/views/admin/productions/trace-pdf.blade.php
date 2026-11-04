<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #111; }
    h1 { font-size: 15pt; color: #1F4E3D; margin: 0 0 2px; } h2 { font-size: 10.5pt; color: #1F4E3D; margin: 14px 0 4px; }
    .muted { color: #555; } table { width: 100%; border-collapse: collapse; }
    th { background: #1F4E3D; color: #fff; text-align: left; padding: 4px; font-size: 8pt; } td { padding: 3px 4px; border-bottom: 1px solid #D0D7D3; vertical-align: top; }
    .facts td { border: none; padding: 1px 6px 1px 0; } .facts td.k { color: #555; width: 22%; } .num { text-align: right; }
</style></head><body>
    @php($d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d M Y') : '—')
    <h1>Batch trace report — {{ $batch->batch_id }}</h1>
    <p class="muted">Exalto Green Ltd · {{ $batch->product?->type }} · prepared {{ now()->format('d M Y H:i') }}</p>
    <table class="facts">
        <tr><td class="k">Production date</td><td>{{ $d($batch->production_date) }}</td><td class="k">Release status</td><td>{{ \App\Models\Production::QC_STATUSES[$batch->qc_status] ?? $batch->qc_status }}</td></tr>
        <tr><td class="k">Packaging date</td><td>{{ $d($batch->packaging_date) }}</td><td class="k">Line / shift</td><td>{{ $batch->production_line ?: '—' }} / {{ $batch->shift ?: '—' }}</td></tr>
        <tr><td class="k">Best before</td><td>{{ $d($batch->best_before_date) }}</td><td class="k">Operator</td><td>{{ $batch->responsible_staff ?: '—' }}</td></tr>
        <tr><td class="k">Use by / expiry</td><td>{{ $d($batch->expiry_date) }}</td><td class="k">Units produced / damaged / sold / in stock</td><td>{{ $summary['produced'] }} / {{ $summary['damaged'] }} / {{ $summary['sold'] }} / {{ $summary['remaining'] }}</td></tr>
    </table>

    <h2>Ingredients (backward trace)</h2>
    <table><tr><th>Material</th><th>Supplier</th><th>Supplier lot</th><th>Received</th><th>Expiry</th><th>Inspection</th><th class="num">Qty used</th><th>Also used in</th></tr>
        @forelse ($ingredients as $r)<tr><td>{{ $r['item'] }}</td><td>{{ $r['supplier'] }}</td><td>{{ $r['supplier_lot'] ?: '—' }}</td><td>{{ $d($r['received']) }}</td><td>{{ $d($r['expiry']) }}</td><td>{{ $r['inspection'] ?: '—' }}</td><td class="num">{{ $r['quantity'] }}</td><td>{{ $r['other_batches'] ?: '—' }}</td></tr>
        @empty<tr><td colspan="8">No raw materials recorded.</td></tr>@endforelse
    </table>

    <h2>Distribution (forward trace)</h2>
    <table><tr><th>Sale</th><th>Date</th><th>Customer</th><th>Phone</th><th>Delivery address</th><th>Delivery</th><th class="num">Qty</th></tr>
        @forelse ($distribution as $r)<tr><td>{{ $r['sale'] }}</td><td>{{ $d($r['date']) }}</td><td>{{ $r['customer'] }}</td><td>{{ $r['phone'] ?: '—' }}</td><td>{{ $r['address'] ?: '—' }}</td><td>{{ $r['delivery'] }}</td><td class="num">{{ $r['quantity'] }}</td></tr>
        @empty<tr><td colspan="7">Nothing sold from this batch yet.</td></tr>@endforelse
    </table>

    <h2>Quality checks</h2>
    <table><tr><th>Checked</th><th>Check</th><th class="num">Measured</th><th>Specification</th><th>Result</th><th>Lab report</th><th>By</th></tr>
        @forelse ($quality as $r)<tr><td>{{ $d($r['checked_at']) }}</td><td>{{ $r['type'] }}</td><td class="num">{{ $r['value'] !== null ? (float) $r['value'].' '.$r['unit'] : '—' }}</td><td>{{ $r['spec'] ?: '—' }}</td><td>{{ $r['result'] }}</td><td>{{ $r['report'] ?: '—' }}</td><td>{{ $r['by'] ?: '—' }}</td></tr>
        @empty<tr><td colspan="7">No quality checks recorded.</td></tr>@endforelse
    </table>
</body></html>
