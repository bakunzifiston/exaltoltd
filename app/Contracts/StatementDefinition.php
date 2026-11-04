<?php

namespace App\Contracts;

use App\Models\FinancialReport;

/**
 * Describes the shape of one financial statement.
 *
 * FinancialReportService owns the arithmetic that is common to every statement — column
 * generation, null-propagating summation, annual totals, comparison — while a definition
 * supplies the parts that differ: which sections exist, which subtotals are derived from
 * which others, and where the figures come from. Adding a statement means adding a
 * definition, not editing the engine.
 */
interface StatementDefinition
{
    /** The FinancialReport::KIND_* value this definition serves. */
    public function kind(): string;

    /**
     * Ordered section key => display label. Sections hold the detail rows a preparer edits.
     *
     * @return array<string, string>
     */
    public function sections(FinancialReport $report): array;

    /**
     * Derived lines, in the order they should appear.
     *
     * Each entry is:
     *   key         unique identifier, also used by the exporters to build live formulas
     *   label       display text
     *   after       section key this line is emitted after
     *   terms       list of ['key' => string, 'sign' => 1|-1] referring to section or result keys
     *   type        'result' for a subtotal, 'check' for an assertion that must equal zero
     *   annual_mode optional per-line override of annualMode()
     *
     * @return array<int, array<string, mixed>>
     */
    public function results(FinancialReport $report): array;

    /**
     * How the annual column is derived from the twelve monthly columns.
     *
     * 'sum' for flows (income statement, cash flow); 'closing' for balances (balance
     * sheet), where adding twelve month-end positions together would be meaningless.
     */
    public function annualMode(): string;

    /**
     * Key of the line every other line is expressed as a percentage of, or null when the
     * statement has no such base. Only the income statement has a meaningful one.
     */
    public function marginBaseKey(): ?string;

    /**
     * A blank editable template.
     *
     * @return array<int, array<string, mixed>>
     */
    public function blankRows(FinancialReport $report): array;

    /**
     * Rows and reporting-basis notes drawn from live system data.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    public function snapshot(FinancialReport $report): array;

    /** Whether a system snapshot can be produced for this statement at all. */
    public function supportsSnapshot(): bool;
}
