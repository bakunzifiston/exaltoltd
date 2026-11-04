<?php

namespace App\Services;

use App\Models\FinancialReport;
use Carbon\CarbonImmutable;

/**
 * The lender and funder ratios: leverage, cover, liquidity and margin.
 *
 * Every ratio propagates missing data the same way the statements do. A ratio whose
 * denominator is missing or zero is reported as unavailable, never as zero or infinity —
 * a debt-to-equity of "0.00" on a business with no recorded equity would be a lie.
 */
class RatioService
{
    public function __construct(
        private readonly FinancialReportService $reports,
        private readonly BalanceSheetService $balanceSheet,
    ) {}

    /**
     * Ratios as at the closing position of a reporting year.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forPeriod(string $startDate, string $asOf): array
    {
        $balance = $this->closing($this->statement(FinancialReport::KIND_BALANCE_SHEET, $startDate, $asOf));
        $income = $this->annual($this->statement(FinancialReport::KIND_INCOME_STATEMENT, $startDate, $asOf));

        $currentAssets = $balance['assets_current'] ?? null;
        $currentLiabilities = $balance['liabilities_current'] ?? null;
        $totalLiabilities = $balance['total_liabilities'] ?? null;
        $equity = $balance['total_equity'] ?? null;
        $totalAssets = $balance['total_assets'] ?? null;

        $inventory = $this->lineClosing($this->statement(FinancialReport::KIND_BALANCE_SHEET, $startDate, $asOf), 'Inventory');

        $revenue = $income['revenue'] ?? null;
        $ebit = $income['ebit'] ?? null;
        $gross = $income['gross'] ?? null;
        $net = $income['net'] ?? null;

        $interest = $this->interestPaid($startDate, $asOf);
        $principal = $this->principalRepaid($startDate, $asOf);

        return [
            $this->row('Current ratio', 'Liquidity', $this->divide($currentAssets, $currentLiabilities), 'x',
                'Current assets divided by current liabilities. Below 1.0 means short-term obligations exceed the assets available to meet them.'),
            $this->row('Quick ratio', 'Liquidity',
                $this->divide($currentAssets === null || $inventory === null ? null : $currentAssets - $inventory, $currentLiabilities), 'x',
                'The same test excluding inventory, which cannot always be turned into cash quickly.'),
            $this->row('Debt to equity', 'Leverage', $this->divide($totalLiabilities, $equity), 'x',
                'Total liabilities divided by total equity. The higher it is, the more of the business is funded by other people.'),
            $this->row('Debt to assets', 'Leverage', $this->divide($totalLiabilities, $totalAssets), 'x',
                'The share of everything owned that is financed by debt.'),
            $this->row('Interest coverage', 'Debt service', $this->divide($ebit, $interest), 'x',
                'Operating result divided by interest paid. It says how many times over the business can pay its interest bill.'),
            $this->row('Debt service coverage', 'Debt service',
                $this->divide($ebit, $interest === null && $principal === null ? null : (float) $interest + (float) $principal), 'x',
                'Operating result against interest plus principal repaid. Lenders usually look for at least 1.25.'),
            $this->row('Gross margin', 'Margin', $this->percentage($gross, $revenue), '%',
                'Gross result as a share of revenue, after the cost of the goods sold.'),
            $this->row('Operating margin', 'Margin', $this->percentage($ebit, $revenue), '%',
                'Operating result as a share of revenue, after operating expenses.'),
            $this->row('Net margin', 'Margin', $this->percentage($net, $revenue), '%',
                'What is left of each franc of revenue after every cost, including tax and interest.'),
            $this->row('Return on equity', 'Return', $this->percentage($net, $equity), '%',
                'Net result against the capital invested in the business.'),
        ];
    }

    /** @var array<string, array> Statements already built, so each is calculated once. */
    private array $statements = [];

    private function statement(string $kind, string $startDate, string $asOf): array
    {
        return $this->statements[$kind.'|'.$startDate.'|'.$asOf] ??= $this->buildStatement($kind, $startDate, $asOf);
    }

    private function buildStatement(string $kind, string $startDate, string $asOf): array
    {
        $report = new FinancialReport([
            'kind' => $kind,
            'mode' => FinancialReport::MODE_ACTUAL,
            'source' => FinancialReport::SOURCE_SYSTEM,
            'start_date' => $startDate,
            'as_of' => $asOf,
        ]);

        [$rows] = $this->reports->snapshot($report);
        $report->fill(['rows' => $rows, 'source_notes' => []]);

        return $this->reports->calculate($report);
    }

    /** Closing position of every total on a balance sheet. */
    private function closing(array $calculation): array
    {
        $out = [];
        foreach ($calculation['totals'] as $key => $values) {
            $out[$key] = $this->lastKnown(array_slice($values, 0, 12));
        }

        return $out;
    }

    /** Twelve-month total of every flow on an income statement. */
    private function annual(array $calculation): array
    {
        $out = [];
        foreach ($calculation['totals'] as $key => $values) {
            $out[$key] = $this->reports->sum(array_slice($values, 0, 12));
        }

        return $out;
    }

    private function lineClosing(array $calculation, string $label): ?float
    {
        foreach ($calculation['lines'] as $line) {
            if (($line['label'] ?? null) === $label) {
                return $this->lastKnown(array_slice($line['values'], 0, 12));
            }
        }

        return null;
    }

    private function lastKnown(array $values): ?float
    {
        for ($i = count($values) - 1; $i >= 0; $i--) {
            if ($values[$i] !== null) {
                return (float) $values[$i];
            }
        }

        return null;
    }

    private function interestPaid(string $startDate, string $asOf): ?float
    {
        return round((float) \App\Models\LoanRepayment::query()
            ->whereDate('paid_at', '>=', CarbonImmutable::parse($startDate)->toDateString())
            ->whereDate('paid_at', '<=', CarbonImmutable::parse($asOf)->toDateString())
            ->sum('interest_portion'), 2);
    }

    private function principalRepaid(string $startDate, string $asOf): ?float
    {
        return round((float) \App\Models\LoanRepayment::query()
            ->whereDate('paid_at', '>=', CarbonImmutable::parse($startDate)->toDateString())
            ->whereDate('paid_at', '<=', CarbonImmutable::parse($asOf)->toDateString())
            ->sum('principal_portion'), 2);
    }

    private function divide(?float $numerator, ?float $denominator): ?float
    {
        if ($numerator === null || $denominator === null || abs($denominator) < 0.01) {
            return null;
        }

        return round($numerator / $denominator, 2);
    }

    private function percentage(?float $numerator, ?float $denominator): ?float
    {
        $value = $this->divide($numerator, $denominator);

        return $value === null ? null : round($value * 100, 1);
    }

    private function row(string $name, string $group, ?float $value, string $unit, string $explanation): array
    {
        return ['name' => $name, 'group' => $group, 'value' => $value, 'unit' => $unit, 'explanation' => $explanation];
    }
}
