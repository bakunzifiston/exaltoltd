<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>{{ $report->title }}</title>
<style>
@page { margin: 28px 30px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #172b3a; }
h1 { font-size: 18px; margin: 0 0 8px; } h2 { font-size: 12px; margin: 12px 0; }
p { line-height: 1.5; } table { border-collapse: collapse; width: 100%; table-layout: fixed; }
th, td { padding: 4px; border-bottom: 1px solid #dce3e9; word-wrap: break-word; }
thead th { background: #243b53; color: white; } thead { display: table-header-group; }
tr { page-break-inside: avoid; } .label { width: 28%; text-align: left; } .number { text-align: right; }
.heading { background: #e8edf2; font-weight: bold; } .total { background: #f2f5f7; font-weight: bold; }
.result { background: #e0f2ee; font-weight: bold; } .check { background: #fde8e6; font-weight: bold; color: #8a1c14; } .page { page-break-before: always; } .muted { color: #526575; }
.notice { background: #fff5d9; padding: 8px; } li { margin-bottom: 6px; }
</style></head><body>
@php($money = fn ($v) => $v === null ? 'n.a.' : ($v < 0 ? '('.number_format(abs($v), 2).')' : number_format($v, 2)))
@foreach(array_chunk($calculation['columns'], 6, true) as $indices)
    <div @class(['page' => !$loop->first])>
        <h1>EXALTO GREEN LTD - {{ $report->title }}</h1>
        <p class="muted">{{ ucfirst($report->mode) }} draft | RWF | Revision {{ $report->version }} | Prepared by {{ $report->prepared_by }}<br>{{ $report->start_date->format('M Y') }} - {{ $report->start_date->copy()->addMonths(11)->format('M Y') }}. Saved {{ $report->updated_at->format('d M Y H:i') }}.</p>
        <p class="notice">Management draft. {{ $calculation['missing'] }} missing inputs. n.a. means unavailable, not zero. See reporting basis and source notes.</p>
        @if(!empty($calculation['checks']) && $loop->first)<p class="notice" style="background:#fde8e6">This statement does not balance: @foreach($calculation['checks'] as $check){{ $check['label'] }} is {{ $money($check['amount']) }} in {{ $calculation['columns'][$check['column']] ?? 'an unknown period' }}@if(!$loop->last); @endif @endforeach. Review the opening balances and subledger completeness.</p>@endif
        <table><thead><tr><th class="label">Description</th>@foreach($indices as $label)<th class="number">{{ $label }}</th>@endforeach<th class="number">{{ $calculation['annual_mode'] === 'closing' ? 'Closing' : 'First 12 months' }}</th></tr></thead><tbody>
        @foreach($calculation['lines'] as $line)
            @if($line['type'] === 'heading')<tr class="heading"><td colspan="{{ count($indices) + 2 }}">{{ $line['label'] }}</td></tr>
            @else<tr class="{{ $line['type'] }}"><td>{{ $line['label'] }}</td>@foreach($indices as $i => $label)<td class="number">{{ $money($line['values'][$i]) }}</td>@endforeach<td class="number">{{ $money($line['annual']) }}</td></tr>@endif
        @endforeach
        </tbody></table>
    </div>
@endforeach
<div class="page"><h1>Annual summary and reporting basis</h1>
    <table><thead><tr><th class="label">Metric</th><th class="number">{{ $calculation['annual_mode'] === 'closing' ? 'Closing position (RWF)' : 'First 12 months (RWF)' }}</th><th class="number">{{ ['revenue' => '% of revenue', 'total_assets' => '% of total assets'][$calculation['margin_base']] ?? '' }}</th></tr></thead><tbody>
    @foreach($calculation['lines'] as $line)@if(in_array($line['type'], ['total', 'result', 'check']))<tr class="{{ $line['type'] }}"><td>{{ $line['label'] }}</td><td class="number">{{ $money($line['annual']) }}</td><td class="number">{{ $line['margin'] === null ? 'n.a.' : number_format($line['margin'] * 100, 1).'%' }}</td></tr>@endif @endforeach
    </tbody></table>
    @if($prior)<h2>Comparison: {{ $prior->title }} ({{ $prior->start_date->format('M Y') }}) vs {{ $report->title }} ({{ $report->start_date->format('M Y') }})</h2><table><thead><tr><th class="label">Metric</th><th>Prior (RWF)</th><th>Current (RWF)</th><th>Change (RWF)</th><th>Change %</th></tr></thead><tbody>@foreach($comparison as $row)<tr><td>{{ $row['label'] }}</td><td class="number">{{ $money($row['prior']) }}</td><td class="number">{{ $money($row['current']) }}</td><td class="number">{{ $money($row['change']) }}</td><td class="number">{{ $row['growth'] === null ? 'n.a.' : number_format($row['growth'] * 100, 1).'%' }}</td></tr>@endforeach</tbody></table>@endif
    <h2>Reporting basis</h2><ul>@foreach($report->source_notes as $note)<li>{{ $note }}</li>@endforeach</ul><p>Change % uses absolute prior totals. Comparisons use the first 12 months. Loan principal and reserves are allocations, not income-statement expenses.</p>
    @if($report->notes)<h2>Report notes</h2><p style="white-space: pre-wrap">{{ $report->notes }}</p>@endif
</div>
</body></html>
