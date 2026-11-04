<?php

namespace App\Statements;

use App\Models\FinancialReport;
use Carbon\CarbonImmutable;

/**
 * Helpers shared by every statement definition. Kept separate from the definitions so the
 * window and template rules cannot drift apart between statements.
 */
class StatementSupport
{
    /** Number of value columns a report carries: twelve months, plus three forecast bands. */
    public static function columnCount(FinancialReport $report): int
    {
        return $report->isFinancialTable() && $report->mode === FinancialReport::MODE_FORECAST ? 15 : 12;
    }

    /**
     * The reporting window and a month template for a system snapshot.
     *
     * The template holds 0 for months inside the window and null for months beyond the
     * cutoff, so a period that has not happened yet reads as unavailable rather than as
     * a genuine zero.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: array<int, float|null>}
     */
    public static function window(FinancialReport $report): array
    {
        $start = CarbonImmutable::parse($report->start_date)->startOfMonth();
        $end = CarbonImmutable::parse($report->as_of)->min($start->addYear()->subDay());

        $template = [];
        for ($i = 0; $i < 12; $i++) {
            $template[] = $start->addMonths($i)->greaterThan($end) ? null : 0;
        }

        return [$start, $end, $template];
    }

    /** Month-end dates for the twelve reporting columns. */
    public static function monthEnds(FinancialReport $report): array
    {
        $start = CarbonImmutable::parse($report->start_date)->startOfMonth();
        $ends = [];
        for ($i = 0; $i < 12; $i++) {
            $ends[] = $start->addMonths($i)->endOfMonth();
        }

        return $ends;
    }

    /**
     * Build blank rows from a section => labels map.
     *
     * @param  array<string, array<int, string>>  $labels
     */
    public static function rowsFromLabels(array $labels, int $columns): array
    {
        $rows = [];
        foreach ($labels as $section => $names) {
            foreach ($names as $name) {
                $rows[] = ['section' => $section, 'label' => $name, 'values' => array_fill(0, $columns, null)];
            }
        }

        return $rows;
    }
}
