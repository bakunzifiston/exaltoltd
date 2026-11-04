<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinancialReport;
use App\Services\FinancialReportExportService;
use App\Services\FinancialReportService;
use App\Support\TablePageSize;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FinancialReportController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.reports.financial.index', ['reports' => FinancialReport::latest()->paginate(TablePageSize::resolve($request, 20))->withQueryString()]);
    }

    public function store(Request $request, FinancialReportService $service)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::in(FinancialReport::AVAILABLE_KINDS)],
            'mode' => ['required', Rule::in(array_keys(FinancialReport::MODES))],
            'source' => ['required', Rule::in(array_keys(FinancialReport::SOURCES))],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
            'as_of' => ['exclude_unless:source,system', 'required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:today'],
            'prepared_by' => ['required', 'string', 'max:150'],
        ]);
        $start = CarbonImmutable::parse($data['start_date']);
        if ($start->day !== 1) {
            throw ValidationException::withMessages(['start_date' => 'Choose the first day of the reporting month.']);
        }
        if ($data['mode'] === FinancialReport::MODE_FORECAST && $data['source'] === FinancialReport::SOURCE_SYSTEM) {
            throw ValidationException::withMessages(['source' => 'Forecasts require manual assumptions. Choose blank template.']);
        }
        $report = new FinancialReport($data);
        if ($data['source'] === FinancialReport::SOURCE_SYSTEM) {
            [$rows, $notes] = $service->snapshot($report);
            if (count($rows) > 200) {
                throw ValidationException::withMessages(['source' => 'This snapshot exceeds 200 report lines. Use a reviewed, consolidated manual template. No rows have been discarded or saved.']);
            }
        } else {
            $rows = $service->blankRows($report);
            $notes = ['Manual '.$data['mode'].' draft. Blank means missing; enter zero explicitly where applicable.', 'Template structure adapted from the supplied SHAKY income statements and RWAZK financial table. No historical workbook amounts or tax assumptions are imported.'];
        }
        $report->fill(['rows' => $rows, 'source_notes' => $notes, 'created_by' => $request->user()->id]);
        $report->save();

        return redirect()->route('admin.reports.financial.edit', $report)->with('status', 'Draft created. Review the figures and reporting basis.');
    }

    public function show(Request $request, FinancialReport $financialReport, FinancialReportService $service)
    {
        [$prior, $comparison] = $this->comparison($request, $financialReport, $service);

        return view('admin.reports.financial.show', [
            'report' => $financialReport, 'calculation' => $service->calculate($financialReport),
            'prior' => $prior, 'comparison' => $comparison,
            'comparables' => FinancialReport::where('kind', $financialReport->kind)->where('mode', $financialReport->mode)
                ->whereDate('start_date', '<', $financialReport->start_date)->whereMonth('start_date', $financialReport->start_date->month)->latest()->get(),
        ]);
    }

    public function edit(FinancialReport $financialReport, FinancialReportService $service)
    {
        return view('admin.reports.financial.edit', ['report' => $financialReport, 'columns' => $service->columns($financialReport), 'sections' => $service->sections($financialReport)]);
    }

    public function update(Request $request, FinancialReport $financialReport, FinancialReportService $service)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'], 'prepared_by' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:5000'], 'version' => ['required', 'integer', 'min:1'],
            'rows_json' => ['required', 'json', 'max:200000'],
        ]);
        $rows = json_decode($data['rows_json'], true);
        $sections = array_keys($service->sections($financialReport));
        Validator::make(['rows' => $rows], [
            'rows' => ['required', 'array', 'list', 'min:1', 'max:200'],
            'rows.*' => ['array:section,label,values'],
            'rows.*.section' => ['required', Rule::in($sections)],
            'rows.*.label' => ['required', 'string', 'max:150'],
            'rows.*.values' => ['required', 'array', 'list', 'size:'.count($service->columns($financialReport))],
            'rows.*.values.*' => ['nullable', 'numeric', 'between:-999999999999,999999999999', 'decimal:0,2'],
        ])->validate();
        foreach ($rows as &$row) {
            $row['values'] = array_map(fn ($v) => $v === null ? null : round((float) $v, 2), $row['values']);
        }
        unset($row);
        $updated = FinancialReport::whereKey($financialReport->id)->where('version', $data['version'])->update([
            'title' => $data['title'], 'prepared_by' => $data['prepared_by'], 'notes' => $data['notes'] ?? null,
            'rows' => json_encode($rows, JSON_THROW_ON_ERROR), 'version' => $data['version'] + 1, 'updated_at' => now(),
        ]);
        abort_unless($updated, 409, 'This report was edited by someone else. Reload before saving.');

        return redirect()->route('admin.reports.financial.show', $financialReport)->with('status', 'Report saved. Preview and downloads now use this revision.');
    }

    public function destroy(FinancialReport $financialReport)
    {
        $financialReport->delete();

        return redirect()->route('admin.reports.financial.index')->with('status', 'Report deleted.');
    }

    public function export(Request $request, FinancialReport $financialReport, string $format, FinancialReportService $service, FinancialReportExportService $exporter)
    {
        abort_unless(in_array($format, ['pdf', 'xlsx'], true), 404);
        [$prior] = $this->comparison($request, $financialReport, $service);
        $name = 'shaky-financial-report-'.$financialReport->id.'-v'.$financialReport->version.'.'.$format;
        if ($format === 'pdf') {
            $pdf = $exporter->pdf($financialReport, $prior);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => ($request->boolean('preview') ? 'inline' : 'attachment').'; filename="'.$name.'"', 'Cache-Control' => 'private, no-store']);
        }

        return response()->streamDownload(function () use ($exporter, $financialReport, $prior): void {
            $exporter->excel($financialReport, $prior)->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }

    private function comparison(Request $request, FinancialReport $report, FinancialReportService $service): array
    {
        $data = $request->validate(['compare_id' => ['nullable', 'integer', 'exists:financial_reports,id']]);
        $prior = isset($data['compare_id']) ? FinancialReport::findOrFail($data['compare_id']) : null;
        if ($prior) {
            abort_unless($prior->id !== $report->id && $prior->kind === $report->kind && $prior->mode === $report->mode && $prior->start_date->month === $report->start_date->month && $prior->start_date->lt($report->start_date), 422, 'Compare an earlier report of the same format, mode and starting month.');
        }

        return [$prior, $prior ? $service->comparison($report, $prior) : []];
    }
}
