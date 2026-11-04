<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\EquityMovement;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialReport;
use App\Models\FixedAsset;
use App\Models\InventoryRecord;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\OpeningBalance;
use App\Models\Product;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The three statements must tie to each other. These tests are the reason the subledgers
 * exist: a balance sheet that does not balance, or a cash flow that does not reconcile to
 * the cash on the balance sheet, is worse than no statement at all.
 */
class FinancialStatementArticulationTest extends TestCase
{
    use RefreshDatabase;

    private const START = '2025-01-01';

    private const AS_OF = '2025-12-31';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedScenario();
    }

    /**
     * A full trading year: opening position, purchases and supplier payments, delivered
     * and undelivered sales, collections, expenses, a depreciating machine, a loan drawn
     * and partly repaid, and capital introduced.
     */
    private function seedScenario(): void
    {
        // Cut-over. Assets 1,000,000 = liabilities 0 + equity 1,000,000.
        foreach ([
            OpeningBalance::CASH => 1_000_000,
            OpeningBalance::SHARE_CAPITAL => 1_000_000,
        ] as $line => $amount) {
            OpeningBalance::create(['as_of_date' => '2024-12-31', 'line_key' => $line, 'amount' => $amount]);
        }
        CashAccount::create(['name' => 'Main till', 'type' => 'cash', 'opening_balance' => 1_000_000, 'opening_balance_date' => '2024-12-31']);

        $product = Product::create(['name' => 'Neza Chilli Oil 80g', 'type' => 'Neza Chilli Oil 80g', 'barcode' => 'ART-1', 'price' => 2000]);
        $batch = Production::withoutEvents(fn () => Production::create([
            'batch_id' => 'ART-BATCH', 'product_id' => $product->id, 'quantity_produced' => 1000,
            'quantity_remaining' => 1000, 'damaged' => 0, 'production_date' => '2025-01-02',
        ]));

        // Two purchase lots at different costs, both settled in cash.
        foreach ([['2025-01-05', 100, 500], ['2025-05-05', 100, 600]] as $i => [$date, $qty, $cost]) {
            $record = InventoryRecord::withoutEvents(fn () => InventoryRecord::create([
                'supplier_name' => 'Supplier', 'item_type' => 'Product', 'item_name' => 'Chilli',
                'product_id' => $product->id, 'quantity_in' => $qty, 'quantity_out' => 0, 'damaged' => 0,
                'storage_location' => 'Store', 'record_date' => $date, 'unit_cost' => $cost,
                'total_amount' => $qty * $cost,
            ]));
            SupplierPayment::create([
                'inventory_record_id' => $record->id, 'supplier_name' => 'Supplier',
                'amount' => $qty * $cost, 'method' => 'Cash', 'paid_at' => $date,
            ]);
        }

        // Delivered sales, one collected in full, one left on credit.
        foreach ([['2025-01-10', 40, 80_000, true], ['2025-06-10', 50, 100_000, false]] as $i => [$date, $qty, $total, $collected]) {
            $sale = Sale::withoutEvents(fn () => Sale::create([
                'sales_id' => 'ART-SALE-'.$i, 'customer_name' => 'Customer', 'total_revenue' => $total,
                'amount_paid' => $collected ? $total : 0, 'payment_status' => $collected ? 'Paid' : 'Credit',
                'delivery_status' => 'Delivered', 'sales_channel' => 'Cash', 'sale_date' => $date,
            ]));
            SaleItem::withoutEvents(fn () => SaleItem::create([
                'sale_id' => $sale->id, 'product_id' => $product->id, 'production_id' => $batch->id,
                'quantity_sold' => $qty, 'unit_price' => 2000, 'line_total' => $total,
            ]));
            if ($collected) {
                SalePayment::create(['sale_id' => $sale->id, 'amount' => $total, 'method' => 'Cash', 'paid_at' => $date]);
            }
        }

        // An undelivered order that has already been paid for: a customer prepayment,
        // not revenue and not a receivable.
        $prepaid = Sale::withoutEvents(fn () => Sale::create([
            'sales_id' => 'ART-PREPAID', 'customer_name' => 'Customer', 'total_revenue' => 30_000,
            'amount_paid' => 30_000, 'payment_status' => 'Paid', 'delivery_status' => 'Pending',
            'sales_channel' => 'Cash', 'sale_date' => '2025-08-01',
        ]));
        SalePayment::create(['sale_id' => $prepaid->id, 'amount' => 30_000, 'method' => 'Cash', 'paid_at' => '2025-08-01']);

        // Operating expenses: one paid on the day, one still unpaid at the year end.
        $category = ExpenseCategory::create(['name' => 'Rent', 'type' => 'operating', 'statement_section' => 'operating']);
        Expense::create(['expense_category_id' => $category->id, 'name' => 'Rent', 'amount' => 10_000, 'currency' => 'RWF', 'expense_date' => '2025-01-15', 'paid_at' => '2025-01-15', 'month' => 1, 'year' => 2025]);
        Expense::create(['expense_category_id' => $category->id, 'name' => 'Rent', 'amount' => 15_000, 'currency' => 'RWF', 'expense_date' => '2025-09-15', 'paid_at' => null, 'month' => 9, 'year' => 2025]);

        FixedAsset::create(['name' => 'Bottling machine', 'acquisition_date' => '2025-01-01', 'cost' => 120_000, 'salvage_value' => 0, 'useful_life_months' => 120]);

        $loan = Loan::create(['lender' => 'Bank of Kigali', 'principal' => 200_000, 'interest_rate' => 12, 'start_date' => '2025-02-01', 'term_months' => 24]);
        LoanRepayment::create(['loan_id' => $loan->id, 'paid_at' => '2025-03-05', 'total_amount' => 12_000, 'principal_portion' => 10_000, 'interest_portion' => 2_000]);

        EquityMovement::create(['type' => EquityMovement::TYPE_CONTRIBUTION, 'amount' => 50_000, 'occurred_at' => '2025-04-01']);
    }

    private function report(string $kind): FinancialReport
    {
        $service = app(FinancialReportService::class);
        $report = new FinancialReport([
            'title' => ucfirst($kind), 'kind' => $kind, 'mode' => FinancialReport::MODE_ACTUAL,
            'source' => FinancialReport::SOURCE_SYSTEM, 'start_date' => self::START,
            'as_of' => self::AS_OF, 'prepared_by' => 'Finance Manager',
        ]);
        [$rows, $notes] = $service->snapshot($report);
        $report->fill(['rows' => $rows, 'source_notes' => $notes]);
        $report->save();

        return $report->refresh();
    }

    public function test_balance_sheet_balances_in_every_month(): void
    {
        $calc = app(FinancialReportService::class)->calculate($this->report(FinancialReport::KIND_BALANCE_SHEET));

        $this->assertSame([], $calc['checks'], 'Balance sheet did not balance: '.json_encode($calc['checks']));

        foreach ($calc['totals']['balance_check'] as $month => $value) {
            $this->assertNotNull($value, 'Month '.$month.' has no balance check.');
            $this->assertEqualsWithDelta(0, $value, 0.01, 'Assets do not equal liabilities plus equity in month '.$month);
        }
    }

    public function test_january_figures_are_exactly_as_computed_by_hand(): void
    {
        $calc = app(FinancialReportService::class)->calculate($this->report(FinancialReport::KIND_BALANCE_SHEET));

        // Cash: 1,000,000 opening - 50,000 supplier + 80,000 customer - 10,000 rent
        //       - 120,000 machine = 900,000.
        // Inventory: 100 bought at 500, 40 sold, 60 left = 30,000.
        // Fixed assets: 120,000 cost less one month of 120,000/120 = 1,000 depreciation.
        $this->assertEqualsWithDelta(1_049_000, $calc['totals']['total_assets'][0], 0.01);
        $this->assertEqualsWithDelta(0, $calc['totals']['total_liabilities'][0], 0.01);
        // Equity: 1,000,000 share capital + 49,000 profit
        //         (80,000 revenue - 20,000 COGS - 10,000 rent - 1,000 depreciation).
        $this->assertEqualsWithDelta(1_049_000, $calc['totals']['total_equity'][0], 0.01);
    }

    public function test_retained_earnings_movement_equals_income_statement_net_income(): void
    {
        $service = app(FinancialReportService::class);
        $income = $service->calculate($this->report(FinancialReport::KIND_INCOME_STATEMENT));
        $balance = $service->calculate($this->report(FinancialReport::KIND_BALANCE_SHEET));

        $profitLine = collect($balance['lines'])->firstWhere('label', 'Profit for the period to date');
        $this->assertNotNull($profitLine);

        // The cumulative profit on the balance sheet at December must equal the income
        // statement's net income for the same twelve months.
        $netAnnual = $service->sum(array_slice($income['totals']['net'], 0, 12));
        $this->assertEqualsWithDelta($netAnnual, $profitLine['values'][11], 0.01);
    }

    public function test_cash_flow_closing_cash_equals_balance_sheet_cash(): void
    {
        $service = app(FinancialReportService::class);
        $cashFlow = $service->calculate($this->report(FinancialReport::KIND_CASH_FLOW));
        $balance = $service->calculate($this->report(FinancialReport::KIND_BALANCE_SHEET));

        $bankLine = collect($balance['lines'])->firstWhere('label', 'Cash and bank');
        $this->assertNotNull($bankLine);

        foreach (range(0, 11) as $month) {
            $this->assertEqualsWithDelta(
                $bankLine['values'][$month],
                $cashFlow['totals']['closing_cash'][$month],
                0.01,
                'Cash flow closing cash disagrees with the balance sheet in month '.$month
            );
        }
    }

    public function test_indirect_reconciliation_agrees_with_direct_operating_cash(): void
    {
        $calc = app(FinancialReportService::class)->calculate($this->report(FinancialReport::KIND_CASH_FLOW));

        foreach ($calc['totals']['reconciliation_check'] as $month => $value) {
            $this->assertEqualsWithDelta(0, $value, 0.01,
                'Indirect reconciliation does not agree with direct operating cash in month '.$month);
        }

        $this->assertSame([], $calc['checks']);
    }

    public function test_prepaid_undelivered_order_is_a_liability_not_revenue(): void
    {
        $service = app(FinancialReportService::class);
        $income = $service->calculate($this->report(FinancialReport::KIND_INCOME_STATEMENT));
        $balance = $service->calculate($this->report(FinancialReport::KIND_BALANCE_SHEET));

        // August: money received, nothing delivered, so no revenue is recognised.
        $this->assertEqualsWithDelta(0, $income['totals']['revenue'][7], 0.01);

        $prepayments = collect($balance['lines'])->firstWhere('label', 'Customer prepayments');
        $this->assertEqualsWithDelta(30_000, $prepayments['values'][7], 0.01);
    }

    public function test_unpaid_expense_is_accrued_and_absent_from_operating_cash(): void
    {
        $service = app(FinancialReportService::class);
        $cashFlow = $service->calculate($this->report(FinancialReport::KIND_CASH_FLOW));
        $balance = $service->calculate($this->report(FinancialReport::KIND_BALANCE_SHEET));

        $accruals = collect($balance['lines'])->firstWhere('label', 'Accrued expenses');
        $this->assertEqualsWithDelta(15_000, $accruals['values'][8], 0.01, 'September rent should be accrued.');

        // Nothing was paid in September, so operating payments are zero that month.
        $this->assertEqualsWithDelta(0, $cashFlow['totals']['operating_out'][8], 0.01);
    }

    public function test_loan_repayment_splits_interest_to_expense_and_principal_to_financing(): void
    {
        $service = app(FinancialReportService::class);
        $income = $service->calculate($this->report(FinancialReport::KIND_INCOME_STATEMENT));
        $cashFlow = $service->calculate($this->report(FinancialReport::KIND_CASH_FLOW));

        // March: 2,000 interest is an expense, 10,000 principal is a financing outflow.
        $this->assertEqualsWithDelta(2_000, $income['totals']['other'][2], 0.01);
        $this->assertEqualsWithDelta(-10_000, $cashFlow['totals']['financing'][2], 0.01);
        $this->assertEqualsWithDelta(2_000, $cashFlow['totals']['operating_out'][2], 0.01);
    }

    public function test_preview_and_index_pages_render_for_every_statement(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);

        $this->actingAs($owner)->get(route('admin.reports.financial.index'))
            ->assertOk()
            ->assertSee('Balance sheet')
            ->assertSee('Cash flow statement');

        foreach (FinancialReport::AVAILABLE_KINDS as $kind) {
            $report = $this->report($kind);

            $this->actingAs($owner)->get(route('admin.reports.financial.show', $report))
                ->assertOk()
                ->assertSee($report->title);

            $this->actingAs($owner)->get(route('admin.reports.financial.edit', $report))->assertOk();
        }
    }

    public function test_a_balance_sheet_that_does_not_balance_is_reported_on_screen(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);
        $report = $this->report(FinancialReport::KIND_BALANCE_SHEET);

        // Corrupt one saved figure so the statement can no longer balance.
        $rows = $report->rows;
        $rows[0]['values'][0] = (float) $rows[0]['values'][0] + 5_000;
        $report->forceFill(['rows' => $rows])->save();

        $calc = app(FinancialReportService::class)->calculate($report->refresh());
        $this->assertNotEmpty($calc['checks'], 'A broken balance sheet must fail its check.');

        $this->actingAs($owner)->get(route('admin.reports.financial.show', $report))
            ->assertOk()
            ->assertSee('This statement does not balance.');
    }

    public function test_exports_produce_real_files_for_the_new_statements(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);

        foreach ([FinancialReport::KIND_BALANCE_SHEET, FinancialReport::KIND_CASH_FLOW] as $kind) {
            $report = $this->report($kind);

            $pdf = $this->actingAs($owner)->get(route('admin.reports.financial.export', ['financialReport' => $report, 'format' => 'pdf']));
            $pdf->assertOk();
            $this->assertStringStartsWith('%PDF-', $pdf->getContent());

            $xlsx = $this->actingAs($owner)->get(route('admin.reports.financial.export', ['financialReport' => $report, 'format' => 'xlsx']));
            $xlsx->assertOk();
            $this->assertStringStartsWith('PK', $xlsx->streamedContent());
        }
    }

    /**
     * A snapshot reads batches and raw-material lots once, however many month columns it
     * has. Before the date-series rewrite one balance sheet rescanned productions and lots
     * for every month and for every statement it rebuilt: 450 queries, 606 for cash flow.
     */
    public function test_snapshots_read_batches_and_lots_once(): void
    {
        $service = app(FinancialReportService::class);
        // The first statement costs any uncosted sale lines once; measure the steady state.
        app(\App\Services\SaleCostingService::class)->recost();

        foreach ([FinancialReport::KIND_BALANCE_SHEET, FinancialReport::KIND_CASH_FLOW] as $kind) {
            app()->forgetScopedInstances();
            $report = new FinancialReport([
                'title' => $kind, 'kind' => $kind, 'mode' => FinancialReport::MODE_ACTUAL,
                'source' => FinancialReport::SOURCE_SYSTEM, 'start_date' => self::START,
                'as_of' => self::AS_OF, 'prepared_by' => 'Finance Manager',
            ]);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $service->snapshot($report);
            $queries = array_column(DB::getQueryLog(), 'query');
            DB::disableQueryLog();

            $fullReads = fn (string $table) => count(array_filter($queries, fn ($q) => str_starts_with($q, 'select') && ! str_contains($q, 'aggregate') && preg_match('/from "'.$table.'"(?! where "\w+"\."id" in)/', $q)));
            $this->assertLessThanOrEqual(1, $fullReads('productions'), $kind.' re-read every batch.');
            $this->assertLessThanOrEqual(1, $fullReads('inventory_records'), $kind.' re-read every lot.');
            // Small indexed sums per month remain (cash, receivables, credit notes, payables…).
            $this->assertLessThan(380, count($queries), $kind.' ran '.count($queries).' queries.');
        }
    }
}
