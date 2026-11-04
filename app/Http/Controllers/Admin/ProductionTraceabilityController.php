<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AuthorizesProduction;
use App\Http\Controllers\Controller;
use App\Models\BatchQualityCheck;
use App\Models\InventoryRecord;
use App\Models\Production;
use App\Models\TraceIncident;
use App\Services\TraceabilityExportService;
use App\Services\TraceabilityService;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Quality checks, release, incidents, labels and trace reports for production batches. */
class ProductionTraceabilityController extends Controller
{
    use AuthorizesProduction;

    public function storeQualityCheck(Request $request, Production $production): RedirectResponse
    {
        $this->authorizeProduction($production);
        $data = $request->validate([
            'check_type' => ['required', Rule::in(array_keys(BatchQualityCheck::TYPES))],
            'measured_value' => ['nullable', 'numeric', 'between:-999999999,999999999'],
            'unit' => ['nullable', 'string', 'max:20'],
            'spec_min' => ['nullable', 'numeric', 'between:-999999999,999999999'],
            'spec_max' => ['nullable', 'numeric', 'between:-999999999,999999999', Rule::when($request->filled('spec_min'), ['gte:spec_min'])],
            'result' => ['required', Rule::in(array_keys(BatchQualityCheck::RESULTS))],
            'lab_report_ref' => ['nullable', 'string', 'max:100'],
            'checked_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $check = $production->qualityChecks()->create($data + ['checked_by' => $request->user()->id]);
        $warning = $check->isOutOfSpec() && $check->result === 'pass'
            ? ' The measured value is outside the limits entered, but the check was recorded as a pass.' : '';

        return $this->back($production, 'quality', 'Quality check recorded.'.$warning);
    }

    public function destroyQualityCheck(Production $production, BatchQualityCheck $check): RedirectResponse
    {
        $this->authorizeProduction($production);
        abort_unless($check->production_id === $production->id, 404);
        abort_unless(request()->user()->can('manage-all-production'), 403);
        $check->delete();

        return $this->back($production, 'quality', 'Quality check removed.');
    }

    /** Release, hold or reject a batch. Only managers decide whether a batch may be sold. */
    public function release(Request $request, Production $production): RedirectResponse
    {
        abort_unless($request->user()->can('manage-all-production'), 403);
        $data = $request->validate([
            'qc_status' => ['required', Rule::in(array_keys(Production::QC_STATUSES))],
            'release_notes' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => in_array($request->input('qc_status'), [Production::QC_ON_HOLD, Production::QC_REJECTED], true))],
        ], ['release_notes.required' => 'Say why the batch is held or rejected.']);

        $production->forceFill($data + ['released_by' => $request->user()->id, 'released_at' => now()])->save();

        return $this->back($production, 'quality', 'Batch is now '.strtolower(Production::QC_STATUSES[$data['qc_status']]).'.');
    }

    public function storeIncident(Request $request, Production $production): RedirectResponse
    {
        $this->authorizeProduction($production);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(TraceIncident::TYPES))],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'actions_taken' => ['nullable', 'string', 'max:5000'],
            'opened_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $production->incidents()->create($data + ['status' => 'open', 'created_by' => $request->user()->id]);

        return $this->back($production, 'incidents', 'Incident recorded.');
    }

    public function closeIncident(Request $request, Production $production, TraceIncident $incident): RedirectResponse
    {
        $this->authorizeProduction($production);
        abort_unless($incident->production_id === $production->id, 404);
        $data = $request->validate([
            'actions_taken' => ['required', 'string', 'max:5000'],
            'disposition' => ['required', 'string', 'max:150'],
        ]);

        $incident->update($data + ['status' => 'closed', 'closed_at' => today()]);

        return $this->back($production, 'incidents', 'Incident closed.');
    }

    /** Printable batch labels: product, batch, date codes and a QR code to the trace page. */
    public function label(Request $request, Production $production): View
    {
        $this->authorizeProduction($production);
        $copies = max(1, min(60, $request->integer('copies', 12)));
        $traceUrl = route('admin.productions.show', ['production' => $production, 'tab' => 'traceability']);

        $qr = (new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'addQuietzone' => true,
            'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
        ])))->render($traceUrl);

        return view('admin.productions.label', ['production' => $production->load('product'), 'copies' => $copies, 'qr' => $qr]);
    }

    public function export(Production $production, string $format, TraceabilityExportService $exporter): Response
    {
        $this->authorizeProduction($production);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);
        $name = 'trace-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $production->batch_id).'.'.$format;

        if ($format === 'pdf') {
            return response($exporter->batchPdf($production), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$name.'"',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response()->streamDownload(fn () => $exporter->batchWorkbook($production)->save('php://output'), $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** Forward trace from an ingredient lot: affected batches and customers. */
    public function recall(InventoryRecord $inventoryRecord, TraceabilityService $trace): View
    {
        return view('admin.inventory-records.recall', [
            'lot' => $inventoryRecord->load('supplier'),
            'recall' => $trace->recall($inventoryRecord),
            'incidents' => $inventoryRecord->incidents()->latest('opened_at')->get(),
        ]);
    }

    public function recallExport(InventoryRecord $inventoryRecord, TraceabilityExportService $exporter): Response
    {
        return response()->streamDownload(fn () => $exporter->recallWorkbook($inventoryRecord)->save('php://output'),
            'recall-trace-lot-'.$inventoryRecord->id.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'private, no-store',
            ]);
    }

    /** Raise an incident against an ingredient lot, from the recall page. */
    public function storeLotIncident(Request $request, InventoryRecord $inventoryRecord): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(TraceIncident::TYPES))],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'opened_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $inventoryRecord->incidents()->create($data + ['status' => 'open', 'created_by' => $request->user()->id]);

        return redirect()->route('admin.inventory-records.recall', $inventoryRecord)->with('status', 'Incident recorded against this delivery.');
    }

    private function back(Production $production, string $tab, string $status): RedirectResponse
    {
        return redirect()->route('admin.productions.show', ['production' => $production, 'tab' => $tab])->with('status', $status);
    }
}
