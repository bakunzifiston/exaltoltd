<?php

namespace App\Services;

use App\Models\EquityMovement;
use App\Models\Expense;
use App\Models\FinancialReport;
use App\Models\FixedAsset;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\SalePayment;
use App\Models\SupplierPayment;
use App\Statements\StatementSupport;
use App\Support\DateBounds;
use Carbon\CarbonImmutable;

/**
 * Direct-method cash flow statement, with an indirect reconciliation appended.
 *
 * Direct is the primary presentation because it proves itself: opening cash plus the net
 * movement must equal the closing cash on the balance sheet for the same month end. The
 * indirect block is emitted alongside it as a second section whose total must agree with
 * the direct operating figure; a disagreement is a real data problem and shows as a
 * non-zero reconciliation check rather than being hidden.
 */
class CashFlowService
{
    public function __construct(private readonly BalanceSheetService $balanceSheet) {}

    public function snapshot(FinancialReport $report): array
    {
        $this->balanceSheet->prime($report);

        [$start, $end] = StatementSupport::window($report);
        // The last column stops at the cutoff date, as the income statement does.
        $available = array_map(fn (CarbonImmutable $m) => ! $m->startOfMonth()->greaterThan($end), StatementSupport::monthEnds($report));
        $monthEnds = array_map(fn (CarbonImmutable $m) => $m->min($end), StatementSupport::monthEnds($report));

        $rows = [];
        $put = function (string $section, string $label, callable $forMonth) use (&$rows, $monthEnds, $available): void {
            $values = [];
            foreach ($monthEnds as $i => $monthEnd) {
                $values[] = $available[$i] ? round($forMonth($monthEnd->startOfMonth(), $monthEnd), 2) : null;
            }
            $rows[] = ['section' => $section, 'label' => $label, 'values' => $values];
        };

        // Plain bounds rather than whereDate(), so the date indexes are used.
        $between = fn ($query, CarbonImmutable $from, CarbonImmutable $to, string $column) => $query
            ->where($column, '>=', $from->toDateString())
            ->where($column, '<=', DateBounds::endOfDay($to));

        // ── Operating ────────────────────────────────────────────────────────
        $put('operating_in', 'Receipts from customers', fn ($f, $t) => (float) $between(SalePayment::query(), $f, $t, 'paid_at')->sum('amount'));

        $put('operating_out', 'Payments to suppliers', fn ($f, $t) => (float) $between(SupplierPayment::query(), $f, $t, 'paid_at')->sum('amount'));
        $put('operating_out', 'Operating and other expenses paid', fn ($f, $t) => (float) $between(Expense::query()->whereNotNull('paid_at')->where('currency', 'RWF'), $f, $t, 'paid_at')->sum('amount'));
        $put('operating_out', 'Interest paid', fn ($f, $t) => (float) $between(LoanRepayment::query(), $f, $t, 'paid_at')->sum('interest_portion'));

        // ── Investing ────────────────────────────────────────────────────────
        // Signed: purchases are outflows, disposal proceeds inflows.
        $put('investing', 'Purchase of fixed assets', fn ($f, $t) => -1 * (float) $between(FixedAsset::query(), $f, $t, 'acquisition_date')->sum('cost'));
        $put('investing', 'Proceeds from asset disposals', fn ($f, $t) => (float) $between(FixedAsset::query()->whereNotNull('disposal_date'), $f, $t, 'disposal_date')->sum('disposal_proceeds'));

        // ── Financing ────────────────────────────────────────────────────────
        $put('financing', 'Loan drawdowns', fn ($f, $t) => (float) $between(Loan::query(), $f, $t, 'start_date')->sum('principal'));
        $put('financing', 'Loan principal repaid', fn ($f, $t) => -1 * (float) $between(LoanRepayment::query(), $f, $t, 'paid_at')->sum('principal_portion'));
        $put('financing', 'Capital introduced', fn ($f, $t) => (float) $between(EquityMovement::query()->whereIn('type', [EquityMovement::TYPE_SHARE_CAPITAL, EquityMovement::TYPE_CONTRIBUTION]), $f, $t, 'occurred_at')->sum('amount'));
        $put('financing', 'Owner drawings', fn ($f, $t) => -1 * (float) $between(EquityMovement::query()->where('type', EquityMovement::TYPE_DRAWING), $f, $t, 'occurred_at')->sum('amount'));

        // ── Opening cash ─────────────────────────────────────────────────────
        // Taken from the balance sheet so the two statements share one cash definition.
        $put('cash_opening', 'Cash at start of month', fn ($f, $t) => $this->balanceSheet->metricAt('cash', $f->subDay()));

        // ── Indirect reconciliation ──────────────────────────────────────────
        foreach ($this->reconciliationRows($report, $monthEnds, $available) as $row) {
            $rows[] = $row;
        }

        $notes = [
            'Frozen system snapshot as of '.$end->toDateString().'. Each column shows cash movement during that month, except the opening and closing cash lines which are positions.',
            'Direct method: actual receipts and payments, not accruals. Closing cash must equal the cash line on the balance sheet for the same month end.',
            'The reconciliation section restates net income as operating cash. Its total must equal net cash from operating activities above; the reconciliation check line shows the difference and must be zero.',
            'Interest paid is presented within operating activities and principal repayments within financing, following the split recorded on each loan repayment.',
            'A cash movement can only appear here if it was recorded. Expenses with no payment date, and supplier invoices with no recorded payment, are treated as unpaid. All amounts are RWF.',
        ];

        if (SupplierPayment::query()->count() === 0) {
            $notes[] = 'No supplier payments have been recorded, so the statement shows nothing leaving the business for inventory. Record supplier payments before relying on operating cash flow.';
        }

        return [$rows, $notes];
    }

    /**
     * Net income restated as operating cash:
     *   profit + depreciation - increase in receivables + increase in prepayments
     *          - increase in inventory + increase in payables + increase in accruals
     *
     * Each movement is the change between the previous month end and this one.
     */
    private function reconciliationRows(FinancialReport $report, array $monthEnds, array $available): array
    {
        $profit = $this->balanceSheet->monthlyProfit($report);
        $depreciation = $this->balanceSheet->monthlyDepreciation($report);

        $movement = function (string $metric) use ($monthEnds, $available): array {
            $values = [];
            foreach ($monthEnds as $i => $monthEnd) {
                if (! $available[$i]) {
                    $values[] = null;

                    continue;
                }
                $opening = $monthEnd->startOfMonth()->subDay();
                $values[] = round($this->balanceSheet->metricAt($metric, $monthEnd) - $this->balanceSheet->metricAt($metric, $opening), 2);
            }

            return $values;
        };

        $negate = fn (array $values) => array_map(fn ($v) => $v === null ? null : round(-1 * $v, 2), $values);

        return [
            ['section' => 'reconciliation', 'label' => 'Net income for the month', 'values' => $this->mask($profit, $available)],
            ['section' => 'reconciliation', 'label' => 'Add back depreciation', 'values' => $this->mask($depreciation, $available)],
            ['section' => 'reconciliation', 'label' => 'Movement in receivables', 'values' => $negate($movement('receivables'))],
            ['section' => 'reconciliation', 'label' => 'Movement in inventory', 'values' => $negate($movement('inventory'))],
            ['section' => 'reconciliation', 'label' => 'Movement in payables', 'values' => $movement('payables')],
            ['section' => 'reconciliation', 'label' => 'Movement in customer prepayments', 'values' => $movement('prepayments')],
            ['section' => 'reconciliation', 'label' => 'Movement in accrued expenses', 'values' => $movement('accruals')],
        ];
    }

    private function mask(array $values, array $available): array
    {
        $out = [];
        foreach ($values as $i => $value) {
            $out[] = ($available[$i] ?? false) ? $value : null;
        }

        return $out;
    }
}
