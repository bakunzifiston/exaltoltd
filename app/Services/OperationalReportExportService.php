<?php

namespace App\Services;

use DateTimeImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Writes an OperationalReportService summary as a workbook in the house style of the
 * Raw Material Purchase & Cost of Goods report: a Summary sheet (headline, breakdown
 * tables, how to read) followed by one filterable sheet per set of detail rows.
 *
 * Summary figures are written as values calculated from the detail sheets at download
 * time, so the file opens with every number visible in any viewer.
 */
class OperationalReportExportService
{
    private const GREEN = 'FF1F4E3D';

    private const TINT = 'FFE8F0EC';

    private const ZEBRA = 'FFF7F9F8';

    private const AMBER = 'FFFFF4D6';

    private const RULE = 'FFD0D7D3';

    private const FORMATS = [
        'money' => '#,##0;(#,##0);-',
        'qty' => '#,##0.00;(#,##0.00);-',
        'int' => '#,##0',
        'pct' => '0.0%',
        'date' => 'dd-mmm-yyyy',
        'text' => '@',
    ];

    public function excel(array $report): Xlsx
    {
        $book = new Spreadsheet;
        $book->getProperties()->setCreator('Exalto Green Ltd')->setTitle($report['title']);
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        $this->summary($book->getActiveSheet(), $report);
        foreach ($report['sheets'] as $sheet) {
            $this->detail($book->createSheet(), $sheet);
        }
        $book->setActiveSheetIndex(0);

        return new Xlsx($book);
    }

    private function summary(Worksheet $sheet, array $report): void
    {
        $width = 1 + max([1, ...array_map(fn ($t) => count($t['measures']), $report['tables'])]);
        $last = Coordinate::stringFromColumnIndex($width + 1);

        $sheet->setTitle('Summary')->setShowGridlines(false);
        $sheet->getTabColor()->setARGB(self::GREEN);
        $sheet->getColumnDimension('A')->setWidth(2);
        $sheet->getColumnDimension('B')->setWidth(36);
        for ($c = 3; $c <= $width + 1; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(18);
        }

        $this->text($sheet, 'B2', $report['title']);
        $sheet->getStyle('B2')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB(self::GREEN);
        $this->text($sheet, 'B3', $report['subtitle']);
        $sheet->getStyle('B3')->getFont()->getColor()->setARGB('FF666666');

        $r = 5;
        $this->section($sheet, 'B'.$r, 'Headline');
        foreach ($report['headline'] as $metric) {
            $r++;
            $this->text($sheet, 'B'.$r, $metric['label']);
            $this->value($sheet, 'C'.$r, $metric['value'], $metric['format']);
            if (! empty($metric['note'])) {
                $this->text($sheet, 'D'.$r, $metric['note']);
                $sheet->getStyle('D'.$r)->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF777777');
            }
            $sheet->getStyle("B{$r}:C{$r}")->getFont()->setBold(true)->setSize(11);
            $this->rule($sheet, "B{$r}:C{$r}");
            if (! empty($metric['emphasis'])) {
                $this->fill($sheet, 'C'.$r, self::TINT);
            }
        }

        foreach ($report['tables'] as $table) {
            $r += 2;
            $this->section($sheet, 'B'.$r, $table['title']);
            $r++;
            $this->header($sheet, $r, 2, array_merge([$table['group_label']], array_column($table['measures'], 'label')));

            foreach ($table['lines'] as $i => $line) {
                $r++;
                $this->tableRow($sheet, $r, $table, $line['label'], $line['values']);
                if ($i % 2 === 1) {
                    $this->fill($sheet, 'B'.$r.':'.Coordinate::stringFromColumnIndex(count($table['measures']) + 2).$r, self::ZEBRA);
                }
            }
            $r++;
            $this->tableRow($sheet, $r, $table, 'Total', $table['total']);
            $range = 'B'.$r.':'.Coordinate::stringFromColumnIndex(count($table['measures']) + 2).$r;
            $sheet->getStyle($range)->getFont()->setBold(true);
            $this->fill($sheet, $range, self::TINT);
        }

        $r += 2;
        $this->section($sheet, 'B'.$r, 'How to read this report');
        foreach ($report['notes'] as $note) {
            $r++;
            $this->text($sheet, 'B'.$r, '•  '.$note);
            $sheet->mergeCells("B{$r}:{$last}{$r}");
            $sheet->getStyle('B'.$r)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getRowDimension($r)->setRowHeight(15 * max(1, (int) ceil(mb_strlen($note) / (18 * $width))));
        }
    }

    private function tableRow(Worksheet $sheet, int $r, array $table, string $label, array $values): void
    {
        $this->text($sheet, 'B'.$r, $label);
        foreach ($table['measures'] as $i => $m) {
            $this->value($sheet, Coordinate::stringFromColumnIndex($i + 3).$r, $values[$m['key']], $m['format']);
        }
        $this->rule($sheet, 'B'.$r.':'.Coordinate::stringFromColumnIndex(count($table['measures']) + 2).$r);
    }

    private function detail(Worksheet $sheet, array $data): void
    {
        $sheet->setTitle(mb_substr($data['name'], 0, 31));
        $columns = array_values($data['columns']);
        $keys = array_keys($data['columns']);

        $this->header($sheet, 1, 1, array_column($columns, 0));
        foreach ($columns as $i => [, $format, $width]) {
            $letter = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getColumnDimension($letter)->setWidth($width);
            if ($data['rows'] !== []) {
                $sheet->getStyle($letter.'2:'.$letter.(count($data['rows']) + 1))->getNumberFormat()->setFormatCode(self::FORMATS[$format]);
            }
        }

        $flagColumn = array_search('flag', $keys, true);
        foreach ($data['rows'] as $n => $row) {
            $r = $n + 2;
            foreach ($keys as $i => $key) {
                $value = $row[$key] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $this->value($sheet, [$i + 1, $r], $value, $columns[$i][1], false);
            }
            if ($flagColumn !== false && ($row['flag'] ?? '') !== '') {
                $this->fill($sheet, 'A'.$r.':'.Coordinate::stringFromColumnIndex(count($keys)).$r, self::AMBER);
            }
        }

        $lastColumn = Coordinate::stringFromColumnIndex(count($keys));
        $sheet->setAutoFilter('A1:'.$lastColumn.max(2, count($data['rows']) + 1));
        $sheet->freezePane('A2');
    }

    /** Write a value in its format. Text is always explicit, so a name like "=1+1" stays text. */
    private function value(Worksheet $sheet, array|string $cell, mixed $value, string $format, bool $styled = true): void
    {
        if ($value === null) {
            $sheet->setCellValueExplicit($cell, $format === 'text' || $format === 'date' ? '' : 'n.a.', DataType::TYPE_STRING);

            return;
        }

        match ($format) {
            'text' => $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING),
            'date' => $sheet->setCellValue($cell, ExcelDate::PHPToExcel(new DateTimeImmutable((string) $value))),
            default => $sheet->setCellValueExplicit($cell, (float) $value, DataType::TYPE_NUMERIC),
        };

        if ($styled) {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(self::FORMATS[$format]);
            if ($format !== 'text') {
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }
    }

    private function text(Worksheet $sheet, string $cell, string $value): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
    }

    private function section(Worksheet $sheet, string $cell, string $title): void
    {
        $this->text($sheet, $cell, $title);
        $sheet->getStyle($cell)->getFont()->setBold(true)->setSize(12)->getColor()->setARGB(self::GREEN);
    }

    private function header(Worksheet $sheet, int $row, int $firstColumn, array $labels): void
    {
        foreach ($labels as $i => $label) {
            $this->text($sheet, Coordinate::stringFromColumnIndex($firstColumn + $i).$row, $label);
        }
        $range = Coordinate::stringFromColumnIndex($firstColumn).$row.':'.Coordinate::stringFromColumnIndex($firstColumn + count($labels) - 1).$row;
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::GREEN);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }

    private function fill(Worksheet $sheet, string $range, string $argb): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
    }

    private function rule(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::RULE);
    }
}
