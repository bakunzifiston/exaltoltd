<?php

namespace App\Statements;

use App\Contracts\StatementDefinition;
use App\Models\FinancialReport;
use App\Services\CashFlowService;

/**
 * Direct-method cash flow statement.
 *
 * The closing cash line is the proof: it must equal the cash line on the balance sheet for
 * the same month end. The reconciliation section restates net income as operating cash and
 * must agree with the direct figure, which the reconciliation check line asserts.
 */
class CashFlowDefinition implements StatementDefinition
{
    public const SECTIONS = [
        'operating_in' => 'Operating receipts',
        'operating_out' => 'Operating payments',
        'investing' => 'Investing activities',
        'financing' => 'Financing activities',
        'cash_opening' => 'Cash brought forward',
        'reconciliation' => 'Reconciliation of net income to operating cash',
    ];

    public function __construct(private readonly CashFlowService $cashFlow) {}

    public function kind(): string
    {
        return FinancialReport::KIND_CASH_FLOW;
    }

    public function sections(FinancialReport $report): array
    {
        return self::SECTIONS;
    }

    public function results(FinancialReport $report): array
    {
        return [
            ['key' => 'net_operating', 'label' => 'Net cash from operating activities', 'after' => 'operating_out', 'type' => 'result',
                'terms' => [['key' => 'operating_in', 'sign' => 1], ['key' => 'operating_out', 'sign' => -1]]],
            ['key' => 'net_investing', 'label' => 'Net cash from investing activities', 'after' => 'investing', 'type' => 'result',
                'terms' => [['key' => 'investing', 'sign' => 1]]],
            ['key' => 'net_financing', 'label' => 'Net cash from financing activities', 'after' => 'financing', 'type' => 'result',
                'terms' => [['key' => 'financing', 'sign' => 1]]],
            ['key' => 'net_change', 'label' => 'Net increase / (decrease) in cash', 'after' => 'financing', 'type' => 'result',
                'terms' => [['key' => 'net_operating', 'sign' => 1], ['key' => 'net_investing', 'sign' => 1], ['key' => 'net_financing', 'sign' => 1]]],
            // Opening plus the movement is closing cash. This must agree with the balance
            // sheet; if it does not, one of the subledgers is incomplete.
            ['key' => 'closing_cash', 'label' => 'Cash at end of month', 'after' => 'cash_opening', 'type' => 'result',
                'terms' => [['key' => 'cash_opening', 'sign' => 1], ['key' => 'net_change', 'sign' => 1]],
                'annual_mode' => 'closing'],
            ['key' => 'reconciled_operating', 'label' => 'Operating cash per reconciliation', 'after' => 'reconciliation', 'type' => 'result',
                'terms' => [['key' => 'reconciliation', 'sign' => 1]]],
            ['key' => 'reconciliation_check', 'label' => 'Reconciliation check (must be zero)', 'after' => 'reconciliation', 'type' => 'check',
                'terms' => [['key' => 'reconciled_operating', 'sign' => 1], ['key' => 'net_operating', 'sign' => -1]]],
        ];
    }

    public function annualMode(): string
    {
        return 'sum';
    }

    public function marginBaseKey(): ?string
    {
        // Cash movements are not meaningfully expressed as a percentage of one another.
        return null;
    }

    public function supportsSnapshot(): bool
    {
        return true;
    }

    public function blankRows(FinancialReport $report): array
    {
        return StatementSupport::rowsFromLabels([
            'operating_in' => ['Receipts from customers', 'Other operating receipts'],
            'operating_out' => ['Payments to suppliers', 'Payments to employees', 'Other operating payments', 'Interest paid', 'Tax paid'],
            'investing' => ['Purchase of fixed assets', 'Proceeds from asset disposals'],
            'financing' => ['Loan drawdowns', 'Loan principal repaid', 'Capital introduced', 'Owner drawings'],
            'cash_opening' => ['Cash at start of month'],
            'reconciliation' => ['Net income for the month', 'Add back depreciation', 'Movement in receivables', 'Movement in inventory', 'Movement in payables', 'Movement in customer prepayments', 'Movement in accrued expenses'],
        ], StatementSupport::columnCount($report));
    }

    public function snapshot(FinancialReport $report): array
    {
        return $this->cashFlow->snapshot($report);
    }
}
