# Financial reports

Open **Reports > Financial statements** as Owner or Manager to create, edit, preview and download a statement. Open **Finance Ledgers** to record the underlying cash, asset, loan and capital data the statements read.

Four statements are available: **income statement**, **balance sheet**, **cash flow statement**, and the **RWAZK financial table** (an income statement with an extra financing section for loan principal and reserve allocations).

## The three statements articulate

They are not three independent reports. They tie to each other by construction, and the system asserts it:

- Retained earnings on the balance sheet move by exactly the net income the income statement reports, because the balance sheet reads the same income-statement snapshot rather than recomputing profit.
- Closing cash on the cash flow statement equals the cash line on the balance sheet for the same month end.
- The indirect reconciliation restates net income as operating cash and must agree with the direct figure.

Each statement that can prove itself carries a **check line that must be zero**. When it is not, a red banner names the line and the month, the Excel export colours it, and the figures should not be used until it is resolved. A non-zero check almost always means the opening balances do not balance or a subledger is incomplete.

## Reporting basis

- **Revenue** is recognised on delivery, whether paid or on credit. Pending and returned deliveries are excluded, and collections are never revenue. One rule, `RevenueRecognitionService`, is used by the dashboard and every statement, so the figures cannot disagree between pages.
- **Cost of goods sold** is the FIFO cost of the goods actually delivered, matched to the month of the sale. Raw-material purchases are *not* expensed: a purchase moves value from cash or payables into inventory and reaches the income statement only when the goods are sold.
- **Receivables** follow the same basis as revenue. Money taken for an order that has not been delivered is a customer prepayment, a liability, not a negative receivable.
- **Depreciation** comes from the fixed-asset register, straight line, starting in the month of acquisition, with the final month absorbing rounding so an asset depreciates to exactly its residual value.
- **Loan interest** comes from the repayment register, where it is already split from principal. Interest is an expense and an operating cash outflow; principal is a financing outflow that reduces the liability.
- Expense categories marked as depreciation or interest are **excluded** from the statement, with a note saying how many and why, because those charges already arrive from their registers. The same applies to direct-cost categories, which mirror inventory purchases.
- Non-RWF expenses are excluded with an explicit note. There is no FX conversion.

## Opening balances

The balance sheet starts from one dated cut-over set recorded under **Finance Ledgers > Opening balances**: cash, receivables, inventory, fixed assets at cost, accumulated depreciation, payables, loans outstanding, share capital and retained earnings.

A set that does not balance is **rejected on save**, because every column of every statement built on it would be out by the same amount. Retained earnings is normally the balancing figure.

Transactions dated on or before the cut-over are assumed to be inside those figures and are not counted again. This is deliberately the only bridge to history: the source workbooks are not imported, so their unreconciled differences never have to be resolved first.

## Finance ledgers

| Screen | What it records | Why a statement needs it |
| --- | --- | --- |
| Opening balances | The dated cut-over position | The balance sheet has no starting point without it |
| Cash accounts | Tills, bank accounts, mobile-money wallets | Attributes payments to a real account |
| Supplier payments | Money paid out to suppliers | The only cash-outflow source for purchases |
| Fixed assets | Machinery and equipment with useful life | Drives depreciation and the asset balances |
| Loans | Borrowings and repayments, principal split from interest | Interest expense, financing flows, debt balances |
| Equity | Capital introduced and owner drawings | Equity balances and financing flows |

## Workbook analysis

The three files in `finance/` are reference documents. They have not been edited or imported into the transaction ledger.

| Source | Structure | Findings |
| --- | --- | --- |
| Financial Table RWAZK 056.2026 | Fact May 2025-April 2026; Forecast May 2026-April 2027 plus three later annual columns | Input areas are blank. The Fact total heading incorrectly says 2024-2025. Loan principal and reserves sit inside operative expenses; the app separates financing allocations from earnings. |
| Shaky_Ltd_Income_Statement 2025_2026 | Monthly 2025 and partial 2026 | 2025 input revenue RWF 27,780,500, direct costs RWF 2,715,850, operating expenses RWF 10,873,337. January COGS is omitted by some total formulas. Depreciation starts in February despite the note saying January. Some annual other-expense totals omit setup cost and tax inputs. |
| Shaky_Ltd_Income_Statement_2024_AND_2025 | Summary plus monthly 2024 and 2025 | Summary is hardcoded. 2024 detail costs sum to RWF 665,620 against a summary of RWF 654,000, a difference of RWF 11,620. 2025 revenue is RWF 19,385,500, RWF 8,395,000 below the other file. |

No workbook tax percentage, VAT calculation, price or depreciation assumption is promoted into an application rule. The opening-balance approach means these discrepancies do not block reporting.

## Supported workflow

1. Record the opening balances and the finance ledgers.
2. Choose a statement, actual or forecast, and a start date on the first of a month. January gives a calendar year; May reproduces the RWAZK cycle.
3. Actual drafts can start from a frozen system snapshot through a chosen cutoff, or a blank template. Forecasts start blank.
4. Review and edit. Enter an explicit zero where it applies: null means missing and propagates to every dependent total. Months after the cutoff stay unavailable rather than reading as zero.
5. Save and preview. Annual columns sum the first twelve months for flows, and take the closing position for a balance sheet. Check lines must be zero.
6. Compare against an earlier saved report of the same type, mode and starting month.
7. Export PDF or Excel. The Excel file carries live formulas that recalculate, built from each statement's own definition, and all text is written explicitly as text to prevent formula injection.

## Architecture

`FinancialReportService` owns the arithmetic every statement shares: columns, null-propagating summation in integer cents, annual totals, margins, comparison and check detection. Each statement's shape lives in a `StatementDefinition` (`app/Statements/`) declaring its sections, its derived subtotals as signed terms, and where its figures come from. Adding a statement means adding a definition; the engine, the controller, the four views and both exporters need no changes.

## Deployment

Deploy the code and `composer.lock`, enable the PHP extensions Composer requires (GD and ZIP for PhpSpreadsheet), run `composer install --no-dev --optimize-autoloader`, then `php artisan migrate --force`. Build assets with `npm run build`.

Export libraries are Dompdf and PhpSpreadsheet. PDF remote resource loading, embedded PHP and JavaScript are disabled.

## Verification

`php -d extension=gd -d extension=zip vendor/bin/phpunit` — 38 tests, 204 assertions. Coverage includes statement articulation across all three statements, balance checks in every month, hand-computed January figures, FIFO costing across two cost layers, the absence of purchase/expense double counting, revenue consistency between the dashboard and the statements, prepayment and accrual treatment, the loan principal/interest split, opening-balance rejection when unbalanced, supplier-payment settlement, PDF and XLSX output for every statement, and role restrictions.

## Remaining business inputs

The go-live opening balances are a business input and are not inferred: cash per account, outstanding receivables and payables, inventory value, machinery cost and accumulated depreciation to date, loans outstanding, share capital and retained earnings.

## Not implemented

Double-entry general ledger and trial balance; Rwanda VAT and turnover-tax automation; budget-versus-actual variance; historical transactional backfill or workbook import; multi-currency; per-SKU profitability and the lender ratio pack.
