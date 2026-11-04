<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\OperationalReportExportService;
use App\Services\OperationalReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Raw material, sales and production reports: on-screen preview and Excel download. */
class OperationalReportController extends Controller
{
    public function show(Request $request, string $type, OperationalReportService $reports): View
    {
        [$from, $to] = $this->period($request);

        return view('admin.reports.operational.show', [
            'report' => $reports->build($type, $from, $to),
            'type' => $type,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function export(Request $request, string $type, OperationalReportService $reports, OperationalReportExportService $exporter): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $report = $reports->build($type, $from, $to);

        return response()->streamDownload(function () use ($exporter, $report): void {
            $exporter->excel($report)->save('php://output');
        }, $report['filename'], ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store']);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(Request $request): array
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before:2100-01-01'],
        ]);

        return [
            CarbonImmutable::parse($data['start_date'] ?? now()->startOfYear()->toDateString()),
            CarbonImmutable::parse($data['end_date'] ?? now()->toDateString()),
        ];
    }
}
