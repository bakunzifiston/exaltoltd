<?php

namespace App\Services;

use App\Models\FinancialReport;
use App\Statements\IncomeStatementDefinition;
use App\Statements\StatementRegistry;
use Carbon\CarbonImmutable;

/**
 * The arithmetic shared by every financial statement.
 *
 * Column generation, null-propagating summation, annual totals, margins and period
 * comparison live here. What each statement is made of — its sections, its derived
 * subtotals, where its figures come from — lives in a StatementDefinition, so adding a
 * statement does not mean editing this class.
 */
class FinancialReportService
{
    /**
     * Retained for callers and views that reference the income-statement sections
     * directly. Prefer sections(), which respects the report's own statement type.
     */
    public const SECTIONS = IncomeStatementDefinition::SECTIONS;

    public function __construct(private readonly StatementRegistry $registry) {}

    /** Section key => label for this report's statement type. */
    public function sections(FinancialReport $report): array
    {
        return $this->registry->for($report)->sections($report);
    }

    public function columns(FinancialReport $report): array
    {
        $start = CarbonImmutable::parse($report->start_date)->startOfMonth();
        $columns = [];
        for ($i = 0; $i < 12; $i++) {
            $columns[] = $start->addMonths($i)->format('M Y');
        }
        if ($report->isFinancialTable() && $report->mode === FinancialReport::MODE_FORECAST) {
            for ($i = 1; $i <= 3; $i++) {
                $from = $start->addYears($i);
                $columns[] = $from->format('M Y').' - '.$from->addMonths(11)->format('M Y');
            }
        }

        return $columns;
    }

    public function blankRows(FinancialReport $report): array
    {
        return $this->registry->for($report)->blankRows($report);
    }

    public function snapshot(FinancialReport $report): array
    {
        return $this->registry->for($report)->snapshot($report);
    }

    public function supportsSnapshot(FinancialReport $report): bool
    {
        return $this->registry->for($report)->supportsSnapshot();
    }

    /**
     * Sum that propagates missing values.
     *
     * A single null makes the whole total unavailable rather than silently understating
     * it. Values are added as integer cents so repeated rounding cannot drift.
     */
    public function sum(array $values): ?float
    {
        if (in_array(null, $values, true)) {
            return null;
        }

        return array_sum(array_map(fn ($v) => (int) round($v * 100), $values)) / 100;
    }

    public function calculate(FinancialReport $report): array
    {
        $definition = $this->registry->for($report);
        $columns = $this->columns($report);
        $sections = $definition->sections($report);
        $results = $definition->results($report);
        $rows = $report->rows ?? [];

        $lines = [];
        $totals = [];

        foreach ($sections as $section => $label) {
            $details = array_values(array_filter($rows, fn ($r) => ($r['section'] ?? null) === $section));

            $values = [];
            for ($i = 0; $i < count($columns); $i++) {
                $values[] = $this->sum(array_column(array_column($details, 'values'), $i));
            }
            $totals[$section] = $values;

            $lines[] = ['key' => $section.'_heading', 'label' => $label, 'type' => 'heading', 'values' => []];
            foreach ($details as $row) {
                $lines[] = $row + ['type' => 'detail'];
            }
            $lines[] = ['key' => $section, 'label' => 'Total '.$label, 'type' => 'total', 'values' => $values, 'terms' => null];

            // Emit every derived line anchored to this section, in declaration order.
            foreach ($results as $result) {
                if (($result['after'] ?? null) !== $section) {
                    continue;
                }
                $totals[$result['key']] = $this->combine($result['terms'], $totals, count($columns));
                $lines[] = [
                    'key' => $result['key'],
                    'label' => $result['label'],
                    'type' => $result['type'] ?? 'result',
                    'values' => $totals[$result['key']],
                    'terms' => $result['terms'],
                    'annual_mode' => $result['annual_mode'] ?? null,
                ];
            }
        }

        $defaultMode = $definition->annualMode();
        $baseKey = $definition->marginBaseKey();
        $base = $baseKey !== null && isset($totals[$baseKey])
            ? $this->annual($totals[$baseKey], $defaultMode)
            : null;

        foreach ($lines as &$line) {
            $mode = $line['annual_mode'] ?? $defaultMode;
            $line['annual'] = $line['type'] === 'heading' ? null : $this->annual($line['values'], $mode);
            $line['margin'] = $base && $line['annual'] !== null ? $line['annual'] / $base : null;
        }
        unset($line);

        return [
            'columns' => $columns,
            'lines' => $lines,
            'totals' => $totals,
            'annual_mode' => $defaultMode,
            'margin_base' => $baseKey,
            'missing' => array_sum(array_map(fn ($r) => count(array_filter($r['values'], fn ($v) => $v === null)), $rows)),
            'checks' => $this->failedChecks($lines),
        ];
    }

    /**
     * The annual column.
     *
     * Flows are summed over the first twelve months, deliberately excluding the three
     * later forecast bands. Balances take the closing position instead: adding twelve
     * month-end positions together would be meaningless.
     */
    private function annual(array $values, string $mode): ?float
    {
        $first = array_slice($values, 0, 12);

        if ($mode !== 'closing') {
            return $this->sum($first);
        }

        for ($i = count($first) - 1; $i >= 0; $i--) {
            if ($first[$i] !== null) {
                return $first[$i];
            }
        }

        return null;
    }

    /**
     * Apply a derived line's signed terms across every column.
     *
     * @param  array<int, array{key: string, sign: int}>  $terms
     */
    private function combine(array $terms, array $totals, int $columnCount): array
    {
        $values = [];
        for ($i = 0; $i < $columnCount; $i++) {
            $cents = 0;
            $missing = false;
            foreach ($terms as $term) {
                $value = $totals[$term['key']][$i] ?? null;
                if ($value === null) {
                    $missing = true;

                    break;
                }
                $cents += $term['sign'] * (int) round($value * 100);
            }
            $values[] = $missing ? null : round($cents / 100, 2);
        }

        return $values;
    }

    /**
     * Assertion lines that are not zero. A balance sheet that does not balance, or a cash
     * flow reconciliation that does not agree, must be visible rather than silently wrong.
     */
    private function failedChecks(array $lines): array
    {
        $failed = [];
        foreach ($lines as $line) {
            if (($line['type'] ?? null) !== 'check') {
                continue;
            }
            foreach (array_slice($line['values'], 0, 12) as $i => $value) {
                if ($value !== null && abs($value) >= 0.01) {
                    $failed[] = ['key' => $line['key'], 'label' => $line['label'], 'column' => $i, 'amount' => $value];
                }
            }
        }

        return $failed;
    }

    public function comparison(FinancialReport $report, FinancialReport $prior): array
    {
        $current = $this->calculate($report);
        $previous = $this->calculate($prior);
        $mode = $this->registry->for($report)->annualMode();

        $rows = [];
        foreach ($this->comparisonMetrics($report) as $key => $label) {
            if (! isset($current['totals'][$key], $previous['totals'][$key])) {
                continue;
            }
            $a = $this->annual($previous['totals'][$key], $mode);
            $b = $this->annual($current['totals'][$key], $mode);
            $rows[] = [
                'label' => $label,
                'prior' => $a,
                'current' => $b,
                'change' => $a === null || $b === null ? null : round($b - $a, 2),
                'growth' => $a === null || $b === null || $a == 0 ? null : ($b - $a) / abs($a),
            ];
        }

        return $rows;
    }

    /** Headline metrics worth comparing period on period, per statement type. */
    private function comparisonMetrics(FinancialReport $report): array
    {
        return match ($report->kind) {
            FinancialReport::KIND_BALANCE_SHEET => [
                'assets_current' => 'Current assets', 'assets_non_current' => 'Non-current assets',
                'total_assets' => 'Total assets', 'liabilities_current' => 'Current liabilities',
                'total_liabilities' => 'Total liabilities', 'total_equity' => 'Total equity',
            ],
            FinancialReport::KIND_CASH_FLOW => [
                'operating_in' => 'Operating receipts', 'operating_out' => 'Operating payments',
                'net_operating' => 'Net operating cash', 'net_investing' => 'Net investing cash',
                'net_financing' => 'Net financing cash', 'net_change' => 'Net change in cash',
                'closing_cash' => 'Closing cash',
            ],
            default => [
                'revenue' => 'Revenue', 'costs' => 'Direct costs', 'gross' => 'Gross result',
                'operating' => 'Operating expenses', 'ebit' => 'Operating result / EBIT',
                'other' => 'Other expenses and tax', 'net' => 'Net result',
            ],
        };
    }
}
