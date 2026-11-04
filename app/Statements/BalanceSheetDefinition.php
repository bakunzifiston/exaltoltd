<?php

namespace App\Statements;

use App\Contracts\StatementDefinition;
use App\Models\FinancialReport;
use App\Services\BalanceSheetService;

/**
 * Statement of financial position at each month end.
 *
 * Unlike the income statement, the twelve columns are balances rather than flows, so the
 * annual column is the closing position and never a sum. The balance check line is the
 * statement proving itself: assets less liabilities less equity must be zero.
 */
class BalanceSheetDefinition implements StatementDefinition
{
    public const SECTIONS = [
        'assets_current' => 'Current assets',
        'assets_non_current' => 'Non-current assets',
        'liabilities_current' => 'Current liabilities',
        'liabilities_non_current' => 'Non-current liabilities',
        'equity' => 'Equity',
    ];

    public function __construct(private readonly BalanceSheetService $balanceSheet) {}

    public function kind(): string
    {
        return FinancialReport::KIND_BALANCE_SHEET;
    }

    public function sections(FinancialReport $report): array
    {
        return self::SECTIONS;
    }

    public function results(FinancialReport $report): array
    {
        return [
            ['key' => 'total_assets', 'label' => 'TOTAL ASSETS', 'after' => 'assets_non_current', 'type' => 'result',
                'terms' => [['key' => 'assets_current', 'sign' => 1], ['key' => 'assets_non_current', 'sign' => 1]]],
            ['key' => 'total_liabilities', 'label' => 'TOTAL LIABILITIES', 'after' => 'liabilities_non_current', 'type' => 'result',
                'terms' => [['key' => 'liabilities_current', 'sign' => 1], ['key' => 'liabilities_non_current', 'sign' => 1]]],
            ['key' => 'total_equity', 'label' => 'TOTAL EQUITY', 'after' => 'equity', 'type' => 'result',
                'terms' => [['key' => 'equity', 'sign' => 1]]],
            ['key' => 'liabilities_and_equity', 'label' => 'TOTAL LIABILITIES AND EQUITY', 'after' => 'equity', 'type' => 'result',
                'terms' => [['key' => 'total_liabilities', 'sign' => 1], ['key' => 'total_equity', 'sign' => 1]]],
            // Must be zero. Anything else means the statement does not balance.
            ['key' => 'balance_check', 'label' => 'Balance check (must be zero)', 'after' => 'equity', 'type' => 'check',
                'terms' => [['key' => 'total_assets', 'sign' => 1], ['key' => 'total_liabilities', 'sign' => -1], ['key' => 'total_equity', 'sign' => -1]]],
        ];
    }

    public function annualMode(): string
    {
        return 'closing';
    }

    public function marginBaseKey(): ?string
    {
        // A balance sheet has no revenue line, so a percentage-of-revenue column would be
        // meaningless. Expressing each line as a share of total assets is the convention.
        return 'total_assets';
    }

    public function supportsSnapshot(): bool
    {
        return true;
    }

    public function blankRows(FinancialReport $report): array
    {
        return StatementSupport::rowsFromLabels([
            'assets_current' => ['Cash and bank', 'Accounts receivable', 'Inventory', 'Prepayments and deposits'],
            'assets_non_current' => ['Fixed assets at cost', 'Less accumulated depreciation'],
            'liabilities_current' => ['Accounts payable', 'Taxes payable', 'Loans due within one year'],
            'liabilities_non_current' => ['Loans due after one year'],
            'equity' => ['Share capital', 'Owner contributions', 'Owner drawings', 'Retained earnings brought forward', 'Profit for the period'],
        ], StatementSupport::columnCount($report));
    }

    public function snapshot(FinancialReport $report): array
    {
        return $this->balanceSheet->snapshot($report);
    }
}
