<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Labels – batch {{ $production->batch_id }}</title>
    <style>
        /* Sized for A4 sheets of 3 x 7 labels (63.5 x 38.1 mm), the common office label stock. */
        @page { size: A4; margin: 15mm 7mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111; }
        .toolbar { padding: 12px 16px; background: #f1f5f9; border-bottom: 1px solid #cbd5e1; font-size: 14px; }
        .toolbar button { padding: 6px 14px; margin-right: 8px; }
        .sheet { display: grid; grid-template-columns: repeat(3, 63.5mm); grid-auto-rows: 38.1mm; gap: 0 2.5mm; padding: 16px; }
        .label { display: flex; gap: 2mm; padding: 2mm 2.5mm; border: 1px dashed #cbd5e1; overflow: hidden; }
        .qr { width: 26mm; height: 26mm; flex: none; align-self: center; }
        .qr svg { width: 100%; height: 100%; }
        .text { display: flex; flex-direction: column; justify-content: center; min-width: 0; font-size: 7.5pt; line-height: 1.25; }
        .product { font-weight: bold; font-size: 8.5pt; }
        .batch { font-family: "Courier New", monospace; font-weight: bold; font-size: 9pt; margin: 0.5mm 0; }
        .muted { color: #444; }
        @media print { .toolbar { display: none; } .sheet { padding: 0; } .label { border-color: transparent; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Print</button>
        {{ $copies }} labels for batch <strong>{{ $production->batch_id }}</strong> — {{ $production->product?->type }}. Dashed outlines are hidden when printed.
    </div>
    <div class="sheet">
        @for ($i = 0; $i < $copies; $i++)
            <div class="label">
                <div class="qr">{!! $qr !!}</div>
                <div class="text">
                    <span class="product">{{ $production->product?->type }}</span>
                    <span class="batch">LOT {{ $production->batch_id }}</span>
                    <span>MFD {{ \Illuminate\Support\Carbon::parse($production->production_date)->format('d/m/Y') }}</span>
                    @if ($production->best_before_date)<span>BEST BEFORE {{ $production->best_before_date->format('d/m/Y') }}</span>@endif
                    @if ($production->expiry_date)<span>USE BY {{ $production->expiry_date->format('d/m/Y') }}</span>@endif
                    <span class="muted">Exalto Green Ltd · Made in Rwanda</span>
                </div>
            </div>
        @endfor
    </div>
</body>
</html>
