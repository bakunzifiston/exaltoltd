<?php

namespace App\Statements;

use App\Contracts\StatementDefinition;
use App\Models\FinancialReport;
use InvalidArgumentException;

/**
 * Resolves a report to the definition that describes its shape.
 *
 * The income statement definition serves both the plain income statement and the RWAZK
 * financial table, which differ only by the financing section.
 */
class StatementRegistry
{
    public function __construct(
        private readonly IncomeStatementDefinition $incomeStatement,
        private readonly BalanceSheetDefinition $balanceSheet,
        private readonly CashFlowDefinition $cashFlow,
    ) {}

    public function for(FinancialReport $report): StatementDefinition
    {
        return $this->forKind($report->kind);
    }

    public function forKind(?string $kind): StatementDefinition
    {
        return match ($kind) {
            FinancialReport::KIND_INCOME_STATEMENT, FinancialReport::KIND_FINANCIAL_TABLE => $this->incomeStatement,
            FinancialReport::KIND_BALANCE_SHEET => $this->balanceSheet,
            FinancialReport::KIND_CASH_FLOW => $this->cashFlow,
            default => throw new InvalidArgumentException('No statement definition for kind ['.$kind.'].'),
        };
    }
}
