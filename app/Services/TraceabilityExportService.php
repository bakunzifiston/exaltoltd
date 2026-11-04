<?php

namespace App\Services;

use App\Models\InventoryRecord;
use App\Models\Production;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Trace and recall reports as files to hand to an inspector, a customer or a supplier.
 *
 * Workbooks reuse the house layout of the operational reports: a Summary sheet followed
 * by one filterable sheet per list.
 */
class TraceabilityExportService
{
    public function __construct(
        private readonly TraceabilityService $trace,
        private readonly OperationalReportExportService $excel,
    ) {}

    public function batchWorkbook(Production $batch): Xlsx
    {
        $data = $this->batchData($batch);
        $s = $data['summary'];

        return $this->excel->excel([
            'title' => 'Batch Trace Report – '.$batch->batch_id,
            'subtitle' => 'Exalto Green Ltd  ·  '.($batch->product?->type ?? 'Product').'  ·  Prepared '.now()->format('d M Y H:i'),
            'headline' => [
                ['label' => 'Product', 'value' => $batch->product?->type ?? '—', 'format' => 'text'],
                ['label' => 'Batch / lot number', 'value' => $batch->batch_id, 'format' => 'text', 'emphasis' => true],
                ['label' => 'Production date', 'value' => $this->day($batch->production_date), 'format' => 'date'],
                ['label' => 'Packaging date', 'value' => $this->day($batch->packaging_date), 'format' => 'date'],
                ['label' => 'Best before', 'value' => $this->day($batch->best_before_date), 'format' => 'date'],
                ['label' => 'Use by / expiry', 'value' => $this->day($batch->expiry_date), 'format' => 'date'],
                ['label' => 'Release status', 'value' => Production::QC_STATUSES[$batch->qc_status] ?? $batch->qc_status, 'format' => 'text'],
                ['label' => 'Line / shift', 'value' => trim(($batch->production_line ?: '—').' / '.($batch->shift ?: '—')), 'format' => 'text'],
                ['label' => 'Operator', 'value' => $batch->responsible_staff ?: '—', 'format' => 'text'],
                ['label' => 'Units produced', 'value' => $s['produced'], 'format' => 'qty'],
                ['label' => 'Units damaged', 'value' => $s['damaged'], 'format' => 'qty'],
                ['label' => 'Units sold (net of returns)', 'value' => $s['sold'], 'format' => 'qty'],
                ['label' => 'Units remaining', 'value' => $s['remaining'], 'format' => 'qty'],
                ['label' => 'Customers supplied', 'value' => $s['customers'], 'format' => 'int'],
            ],
            'tables' => [],
            'notes' => [
                'Ingredients lists every raw-material lot this batch drew on, with its supplier and supplier lot, and the other batches that used the same lot.',
                'Distribution lists every sale line supplied from this batch and who received it. Use it to contact customers if the batch must be recalled.',
            ],
            'sheets' => [
                ['name' => 'Ingredients', 'rows' => $data['ingredients'], 'columns' => [
                    'lot' => ['Purchase Record', 'int', 16], 'item' => ['Material', 'text', 26], 'supplier' => ['Supplier', 'text', 28],
                    'supplier_lot' => ['Supplier Lot', 'text', 16], 'lot_number' => ['Our Lot No.', 'text', 14], 'received' => ['Received', 'date', 13],
                    'expiry' => ['Material Expiry', 'date', 15], 'inspection' => ['Inspection', 'text', 14], 'coa' => ['CoA', 'text', 14],
                    'storage' => ['Stored In', 'text', 16], 'quantity' => ['Qty Used', 'qty', 11], 'cost' => ['Cost (RWF)', 'money', 14],
                    'other_batches' => ['Also Used In', 'text', 40],
                ]],
                ['name' => 'Distribution', 'rows' => $data['distribution'], 'columns' => [
                    'sale' => ['Sale', 'text', 16], 'date' => ['Sale Date', 'date', 13], 'customer' => ['Customer', 'text', 28],
                    'phone' => ['Phone', 'text', 16], 'address' => ['Delivery Address', 'text', 34], 'delivery' => ['Delivery Status', 'text', 15],
                    'delivered_at' => ['Delivered', 'date', 13], 'quantity' => ['Qty', 'qty', 10], 'value' => ['Value (RWF)', 'money', 14],
                ]],
                ['name' => 'Quality Checks', 'rows' => $data['quality'], 'columns' => [
                    'checked_at' => ['Checked', 'date', 13], 'type' => ['Check', 'text', 26], 'value' => ['Measured', 'qty', 12],
                    'unit' => ['Unit', 'text', 8], 'spec' => ['Specification', 'text', 18], 'result' => ['Result', 'text', 10],
                    'report' => ['Lab Report', 'text', 16], 'by' => ['Checked By', 'text', 20], 'notes' => ['Notes', 'text', 40],
                ]],
                ['name' => 'Movements', 'rows' => $data['timeline'], 'columns' => [
                    'at' => ['Date', 'date', 13], 'kind' => ['Stock', 'text', 16], 'event' => ['Event', 'text', 28],
                    'quantity' => ['Quantity', 'qty', 12], 'detail' => ['Detail', 'text', 48], 'user' => ['By', 'text', 20],
                ]],
            ],
        ]);
    }

    public function batchPdf(Production $batch): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('admin.productions.trace-pdf', ['batch' => $batch] + $this->batchData($batch))->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }

    public function recallWorkbook(InventoryRecord $lot): Xlsx
    {
        $recall = $this->trace->recall($lot);

        return $this->excel->excel([
            'title' => 'Recall Trace – '.$lot->item_name.' (record #'.$lot->id.')',
            'subtitle' => 'Exalto Green Ltd  ·  Supplier '.$lot->supplier_name.'  ·  Prepared '.now()->format('d M Y H:i'),
            'headline' => [
                ['label' => 'Material', 'value' => $lot->item_name, 'format' => 'text', 'emphasis' => true],
                ['label' => 'Supplier', 'value' => $lot->supplier_name, 'format' => 'text'],
                ['label' => 'Supplier lot', 'value' => $lot->supplier_lot_number ?: '—', 'format' => 'text'],
                ['label' => 'Received', 'value' => $this->day($lot->record_date), 'format' => 'date'],
                ['label' => 'Batches made with it', 'value' => $recall['batches']->count(), 'format' => 'int'],
                ['label' => 'Units shipped from those batches', 'value' => (float) $recall['shipments']->sum('quantity'), 'format' => 'qty'],
                ['label' => 'Customers to contact', 'value' => $recall['customers']->count(), 'format' => 'int'],
            ],
            'tables' => [],
            'notes' => [
                'Affected Batches lists every production batch that drew on this delivery and how much of it each used.',
                'Customers groups every shipment from those batches by customer, with contact details, so each can be reached once.',
            ],
            'sheets' => [
                ['name' => 'Affected Batches', 'rows' => $recall['batches']->map(fn ($b) => [
                    'batch' => $b['batch']->batch_id, 'product' => $b['batch']->product?->type, 'date' => $this->day($b['batch']->production_date),
                    'best_before' => $this->day($b['batch']->best_before_date), 'used' => $b['quantity_used'], 'produced' => $b['summary']['produced'],
                    'sold' => $b['summary']['sold'], 'remaining' => $b['summary']['remaining'], 'status' => Production::QC_STATUSES[$b['batch']->qc_status] ?? $b['batch']->qc_status,
                ])->all(), 'columns' => [
                    'batch' => ['Batch', 'text', 16], 'product' => ['Product', 'text', 26], 'date' => ['Produced', 'date', 13],
                    'best_before' => ['Best Before', 'date', 13], 'used' => ['Qty of Lot Used', 'qty', 15], 'produced' => ['Units Made', 'qty', 12],
                    'sold' => ['Units Sold', 'qty', 12], 'remaining' => ['Units in Stock', 'qty', 14], 'status' => ['Status', 'text', 16],
                ]],
                ['name' => 'Customers', 'rows' => $recall['customers']->map(fn ($c) => [
                    'customer' => $c['customer'], 'phone' => $c['phone'], 'address' => $c['address'], 'quantity' => $c['quantity'],
                    'batches' => implode(', ', $c['batches']), 'last_sale' => $c['last_sale'], 'delivered' => $c['delivered'] ? 'Yes' : 'Not yet',
                ])->all(), 'columns' => [
                    'customer' => ['Customer', 'text', 28], 'phone' => ['Phone', 'text', 16], 'address' => ['Address', 'text', 34],
                    'quantity' => ['Units Received', 'qty', 15], 'batches' => ['Batches', 'text', 24], 'last_sale' => ['Last Sale', 'date', 13],
                    'delivered' => ['Delivered', 'text', 10],
                ]],
                ['name' => 'Shipments', 'rows' => $recall['shipments']->map(fn ($r) => $this->distributionRow($r) + ['batch' => $r['batch']->batch_id])->all(), 'columns' => [
                    'batch' => ['Batch', 'text', 16], 'sale' => ['Sale', 'text', 16], 'date' => ['Sale Date', 'date', 13],
                    'customer' => ['Customer', 'text', 28], 'phone' => ['Phone', 'text', 16], 'delivery' => ['Delivery Status', 'text', 15],
                    'quantity' => ['Qty', 'qty', 10],
                ]],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function batchData(Production $batch): array
    {
        $batch->loadMissing(['product', 'releasedBy']);
        $forward = $this->trace->forward($batch);

        return [
            'summary' => $this->trace->summary($batch, $forward),
            'ingredients' => $this->trace->backward($batch)->map(fn ($row) => [
                'lot' => $row['inventory_id'],
                'item' => $row['lot']?->item_name ?? 'Unknown',
                'supplier' => $row['lot']?->supplier?->name ?? $row['lot']?->supplier_name,
                'supplier_lot' => $row['lot']?->supplier_lot_number,
                'lot_number' => $row['lot']?->lot_number,
                'received' => $this->day($row['lot']?->record_date),
                'expiry' => $this->day($row['lot']?->expiry_date),
                'inspection' => InventoryRecord::INSPECTION_STATUSES[$row['lot']?->inspection_status] ?? null,
                'coa' => $row['lot']?->coa_reference,
                'storage' => $row['lot']?->storage_location,
                'quantity' => $row['quantity'],
                'cost' => $row['cost'],
                'other_batches' => $row['other_batches']->pluck('batch_id')->implode(', '),
            ])->all(),
            'distribution' => $forward->map(fn ($r) => $this->distributionRow($r))->all(),
            'quality' => $batch->qualityChecks()->with('checker:id,name')->get()->map(fn ($c) => [
                'checked_at' => $this->day($c->checked_at),
                'type' => $c->typeLabel(),
                'value' => $c->measured_value,
                'unit' => $c->unit,
                'spec' => trim(($c->spec_min !== null ? '≥ '.(float) $c->spec_min : '').' '.($c->spec_max !== null ? '≤ '.(float) $c->spec_max : '')),
                'result' => ucfirst($c->result),
                'report' => $c->lab_report_ref,
                'by' => $c->checker?->name,
                'notes' => $c->notes,
            ])->all(),
            'timeline' => $this->trace->timeline($batch)->map(fn ($e) => [
                'at' => $this->day($e['at']), 'kind' => $e['kind'], 'event' => $e['event'],
                'quantity' => $e['quantity'], 'detail' => $e['detail'], 'user' => $e['user'],
            ])->all(),
        ];
    }

    private function distributionRow(array $r): array
    {
        return [
            'sale' => $r['sales_id'], 'date' => $r['sale_date'], 'customer' => $r['customer'], 'phone' => $r['phone'],
            'address' => $r['address'], 'delivery' => $r['delivery_status'], 'delivered_at' => $this->day($r['delivered_at']),
            'quantity' => $r['quantity'], 'value' => $r['line_total'],
        ];
    }

    private function day(mixed $date): ?string
    {
        return $date ? substr((string) ($date instanceof \DateTimeInterface ? $date->format('Y-m-d') : $date), 0, 10) : null;
    }
}
