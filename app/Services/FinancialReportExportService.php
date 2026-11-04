<?php

namespace App\Services;

use App\Models\FinancialReport;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FinancialReportExportService
{
    private const MONEY = '#,##0.00;(#,##0.00);"-"';

    public function __construct(private FinancialReportService $service) {}

    public function pdf(FinancialReport $report, ?FinancialReport $prior = null): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('admin.reports.financial.pdf', [
            'report' => $report, 'calculation' => $this->service->calculate($report),
            'prior' => $prior, 'comparison' => $prior ? $this->service->comparison($report, $prior) : [],
        ])->render());
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        $pdf->getCanvas()->page_text(730, 575, '{PAGE_NUM} / {PAGE_COUNT}', null, 8, [0.3, 0.3, 0.3]);

        return $pdf->output();
    }

    public function excel(FinancialReport $report, ?FinancialReport $prior = null): Xlsx
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator($report->prepared_by)->setTitle($report->title);
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $this->statement($book->getActiveSheet(), $report, 'Current report');
        if ($prior) {
            $this->statement($book->createSheet(), $prior, 'Prior report');
            $sheet = $book->createSheet(0)->setTitle('Comparison');
            $sheet->setCellValueExplicit('A1', $report->title.' - comparison (RWF)', DataType::TYPE_STRING);
            $sheet->fromArray(['Metric', $prior->start_date->format('M Y'), $report->start_date->format('M Y'), 'Change (RWF)', 'Change %'], null, 'A3');
            foreach ($this->service->comparison($report, $prior) as $i => $row) {
                $r = $i + 4;
                $sheet->setCellValueExplicit('A'.$r, $row['label'], DataType::TYPE_STRING);
                foreach (['prior', 'current', 'change', 'growth'] as $j => $key) {
                    $sheet->setCellValue([$j + 2, $r], $row[$key] ?? 'n.a.');
                }
            }
            $sheet->getStyle('B4:D10')->getNumberFormat()->setFormatCode(self::MONEY);
            $sheet->getStyle('E4:E10')->getNumberFormat()->setFormatCode('0.0%');
            $sheet->getColumnDimension('A')->setWidth(35);
            foreach (['B', 'C', 'D', 'E'] as $c) {
                $sheet->getColumnDimension($c)->setWidth(22);
            }
            $sheet->setCellValue('A12', 'Comparison is a snapshot of the saved revisions; regenerate after editing either report.');
            $sheet->setCellValue('A13', 'Change % uses absolute prior totals. Zero or missing prior values are n.a.');
            $sheet->setShowGridlines(false);
        }
        $book->setActiveSheetIndex(0);

        return new Xlsx($book);
    }

    private function statement($sheet, FinancialReport $report, string $name): void
    {
        $sheet->setTitle($name)->setShowGridlines(false);
        $calc = $this->service->calculate($report);
        $n = count($calc['columns']);
        $annual = Coordinate::stringFromColumnIndex($n + 2);
        $margin = Coordinate::stringFromColumnIndex($n + 3);
        $sheet->setCellValueExplicit('A1', 'EXALTO GREEN LTD - '.$report->title, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit('A2', ucfirst($report->mode).' draft | RWF | Revision '.$report->version.' | Prepared by '.$report->prepared_by, DataType::TYPE_STRING);
        $sheet->setCellValue('A3', 'Blank inputs mean missing; n.a. totals are unavailable. First 12 months total excludes later forecast years.');
        $closing = $calc['annual_mode'] === 'closing';
        $sheet->fromArray(array_merge(['Description'], $calc['columns'], [
            $closing ? 'Closing position' : 'First 12 months',
            match ($calc['margin_base']) {
                'revenue' => '% of revenue',
                'total_assets' => '% of total assets',
                default => '',
            },
        ]), null, 'A5');
        $sheet->freezePane('B6');
        $sheet->getColumnDimension('A')->setWidth(48);
        for ($i = 2; $i <= $n + 3; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(19);
        }
        $sheet->getRowDimension(5)->setRowHeight(32);
        $sheet->getStyle('A5:'.$margin.'5')->getAlignment()->setWrapText(true);
        $keys = [];
        foreach ($calc['lines'] as $i => $line) {
            if (isset($line['key'])) {
                $keys[$line['key']] = $i + 6;
            }
        }
        $sectionStart = 6;
        foreach ($calc['lines'] as $i => $line) {
            $r = $i + 6;
            $sheet->getRowDimension($r)->setRowHeight(26);
            $sheet->setCellValueExplicit('A'.$r, $line['label'], DataType::TYPE_STRING);
            $sheet->getStyle('A'.$r)->getAlignment()->setWrapText(true);
            if ($line['type'] === 'heading') {
                $sectionStart = $r + 1;
                $sheet->getStyle('A'.$r.':'.$margin.$r)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFE8EDF2');

                continue;
            }
            for ($j = 0; $j < $n; $j++) {
                $c = Coordinate::stringFromColumnIndex($j + 2);
                $cell = $c.$r;
                if ($line['type'] === 'detail') {
                    if ($line['values'][$j] !== null) {
                        $sheet->setCellValue($cell, $line['values'][$j]);
                    }
                    $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FF1557B0');
                } elseif ($line['type'] === 'total') {
                    $count = $r - $sectionStart;
                    $range = $c.$sectionStart.':'.$c.($r - 1);
                    $sheet->setCellValue($cell, $count ? '=IF(COUNT('.$range.')='.$count.',SUM('.$range.'),"n.a.")' : 0);
                } else {
                    // Built from the statement definition's own terms, so a new statement
                    // exports live formulas without the exporter knowing anything about it.
                    $refs = [];
                    $expression = '';
                    foreach ($line['terms'] ?? [] as $term) {
                        if (! isset($keys[$term['key']])) {
                            continue;
                        }
                        $ref = $c.$keys[$term['key']];
                        $refs[] = 'ISNUMBER('.$ref.')';
                        $expression .= ($term['sign'] < 0 ? '-' : ($expression === '' ? '' : '+')).$ref;
                    }
                    $sheet->setCellValue($cell, $refs === []
                        ? 'n.a.'
                        : '=IF(AND('.implode(',', $refs).'),'.$expression.',"n.a.")');
                }
            }
            $mode = $line['annual_mode'] ?? $calc['annual_mode'];
            $sheet->setCellValue($annual.$r, $mode === 'closing'
                ? '=IF(ISNUMBER(M'.$r.'),M'.$r.',"n.a.")'
                : '=IF(COUNT(B'.$r.':M'.$r.')=12,SUM(B'.$r.':M'.$r.'),"n.a.")');

            // Only statements with a meaningful base (revenue, total assets) get a
            // percentage column; a cash flow statement has none.
            if ($calc['margin_base'] !== null && isset($keys[$calc['margin_base']])) {
                $base = '$'.$annual.'$'.$keys[$calc['margin_base']];
                $sheet->setCellValue($margin.$r, '=IF(AND(ISNUMBER('.$annual.$r.'),ISNUMBER('.$base.'),'.$base.'<>0),'.$annual.$r.'/'.$base.',"n.a.")');
            }
            $sheet->getStyle('B'.$r.':'.$annual.$r)->getNumberFormat()->setFormatCode(self::MONEY);
            $sheet->getStyle($margin.$r)->getNumberFormat()->setFormatCode('0.0%');
            if ($line['type'] !== 'detail') {
                $sheet->getStyle('A'.$r.':'.$margin.$r)->getFont()->setBold(true);
            }
            if ($line['type'] === 'check') {
                $sheet->getStyle('A'.$r.':'.$margin.$r)->getFont()->getColor()->setARGB('FFB42318');
            }
        }
        $last = count($calc['lines']) + 5;
        $sheet->getStyle('A5:'.$margin.'5')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF243B53');
        $sheet->getStyle('A5:'.$margin.'5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $r = $last + 3;
        foreach (array_merge($report->source_notes, [$report->notes ?? '']) as $note) {
            $sheet->mergeCells('A'.$r.':'.$margin.$r);
            $sheet->setCellValueExplicit('A'.$r, $note, DataType::TYPE_STRING);
            $sheet->getStyle('A'.$r)->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($r)->setRowHeight(35);
            $r++;
        }
        $sheet->getPageSetup()->setOrientation('landscape')->setPaperSize(8)->setFitToWidth(1)->setFitToHeight(0)->setRowsToRepeatAtTopByStartAndEnd(5, 5);
        $sheet->getPageSetup()->setPrintArea('A1:'.$margin.($r - 1));
    }
}
