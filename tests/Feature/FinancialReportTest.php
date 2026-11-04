<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FinancialReport;
use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);
    }

    private function report(array $overrides = []): FinancialReport
    {
        return FinancialReport::create(array_replace([
            'title' => 'Annual income statement', 'kind' => 'income_statement', 'mode' => 'actual',
            'source' => 'manual', 'start_date' => '2025-01-01', 'prepared_by' => 'Finance Manager',
            'source_notes' => ['Manual figures reviewed for testing.'],
            'rows' => [
                ['section' => 'revenue', 'label' => 'Chilli sales', 'values' => array_fill(0, 12, 1000)],
                ['section' => 'costs', 'label' => 'Raw materials', 'values' => array_fill(0, 12, 200)],
                ['section' => 'operating', 'label' => 'Salaries', 'values' => array_fill(0, 12, 300)],
                ['section' => 'other', 'label' => 'Recorded tax', 'values' => array_fill(0, 12, 50)],
            ],
        ], $overrides));
    }

    public function test_snapshot_uses_delivered_credit_sales_and_excludes_returned_and_out_of_period_activity(): void
    {
        foreach ([['Delivered', '2025-01-04', 1000], ['Returned', '2025-01-05', 400], ['Pending', '2025-01-06', 500], ['Delivered', '2024-12-31', 2000]] as $i => [$status, $date, $amount]) {
            Sale::withoutEvents(fn () => Sale::create(['sales_id' => 'TEST-FIN-'.$i, 'customer_name' => 'Customer', 'total_revenue' => $amount, 'amount_paid' => 0, 'payment_status' => 'Credit', 'delivery_status' => $status, 'sales_channel' => 'Cash', 'sale_date' => $date]));
        }
        // Two FIFO layers at different unit costs. Selling 6 units must consume the 10 @ 20
        // layer first, giving a cost of 120 - not the 500 total that was purchased.
        $product = Product::create(['name' => 'Neza Chilli Oil 80g', 'type' => 'Neza Chilli Oil 80g', 'barcode' => 'FIN-TEST-1', 'price' => 2000]);
        InventoryRecord::withoutEvents(function () use ($product) {
            InventoryRecord::create(['supplier_name' => 'Supplier', 'item_type' => 'Product', 'item_name' => 'Chilli', 'product_id' => $product->id, 'quantity_in' => 10, 'quantity_out' => 0, 'damaged' => 0, 'storage_location' => 'Store', 'record_date' => '2025-01-02', 'unit_cost' => 20, 'total_amount' => 200]);
            InventoryRecord::create(['supplier_name' => 'Supplier', 'item_type' => 'Product', 'item_name' => 'Chilli', 'product_id' => $product->id, 'quantity_in' => 10, 'quantity_out' => 0, 'damaged' => 0, 'storage_location' => 'Store', 'record_date' => '2025-01-03', 'unit_cost' => 30, 'total_amount' => 300]);
        });
        // Attach line detail to the delivered in-period sale so there is something to cost.
        $batch = Production::withoutEvents(fn () => Production::create(['batch_id' => 'FIN-TEST-BATCH', 'product_id' => $product->id, 'quantity_produced' => 100, 'quantity_remaining' => 100, 'damaged' => 0, 'production_date' => '2025-01-03']));
        SaleItem::withoutEvents(fn () => SaleItem::create(['sale_id' => Sale::where('sales_id', 'TEST-FIN-0')->value('id'), 'product_id' => $product->id, 'production_id' => $batch->id, 'quantity_sold' => 6, 'unit_price' => 2000, 'line_total' => 1000]));
        $category = ExpenseCategory::create(['name' => 'Salaries', 'type' => 'operating']);
        $expense = ['expense_category_id' => $category->id, 'name' => 'Salary', 'amount' => 300, 'currency' => 'RWF', 'expense_date' => '2025-01-31', 'month' => 1, 'year' => 2025];
        Expense::create($expense);
        Expense::create(array_replace($expense, ['currency' => 'USD', 'amount' => 100]));
        Expense::create(array_replace($expense, ['amount' => 500]))->delete();
        $this->actingAs($this->owner())->post(route('admin.reports.financial.store'), ['title' => 'System draft', 'kind' => 'income_statement', 'mode' => 'actual', 'source' => 'system', 'start_date' => '2025-01-01', 'as_of' => '2025-01-31', 'prepared_by' => 'Manager'])->assertRedirect();
        $report = FinancialReport::firstOrFail();
        $calc = app(FinancialReportService::class)->calculate($report);
        $this->assertEquals(1000, $calc['totals']['revenue'][0]);
        // FIFO: 6 units from the 20-cost layer. Purchases of 500 are inventory, not expense.
        $this->assertEquals(120, $calc['totals']['costs'][0]);
        $this->assertEquals(580, $calc['totals']['net'][0]);
        $this->assertNull($calc['totals']['net'][1]);
        $this->assertStringContainsString('1 non-RWF expenses excluded', implode(' ', $report->source_notes));
    }

    /**
     * A raw-material purchase entered BOTH as an inventory record and as a COGS-classified
     * expense used to be counted twice. Purchases are no longer expensed at all, so the only
     * cost that reaches the statement is the FIFO cost of what was actually sold.
     */
    public function test_purchases_are_not_expensed_and_cogs_is_not_double_counted(): void
    {
        $product = Product::create(['name' => 'Neza Chilli Oil 80g', 'type' => 'Neza Chilli Oil 80g', 'barcode' => 'DBL-1', 'price' => 2000]);
        InventoryRecord::withoutEvents(fn () => InventoryRecord::create(['supplier_name' => 'Supplier', 'item_type' => 'Product', 'item_name' => 'Chilli', 'product_id' => $product->id, 'quantity_in' => 10, 'quantity_out' => 0, 'damaged' => 0, 'storage_location' => 'Store', 'record_date' => '2025-01-02', 'unit_cost' => 50, 'total_amount' => 500]));

        // The same RWF 500 spend, also keyed in on the Expenses page against a COGS category.
        $cogsCategory = ExpenseCategory::create(['name' => 'Fresh Red Chili', 'type' => 'cogs', 'statement_section' => 'costs']);
        Expense::create(['expense_category_id' => $cogsCategory->id, 'name' => 'Chilli purchase', 'amount' => 500, 'currency' => 'RWF', 'expense_date' => '2025-01-02', 'month' => 1, 'year' => 2025]);

        $sale = Sale::withoutEvents(fn () => Sale::create(['sales_id' => 'DBL-SALE', 'customer_name' => 'Customer', 'total_revenue' => 4000, 'amount_paid' => 0, 'payment_status' => 'Credit', 'delivery_status' => 'Delivered', 'sales_channel' => 'Cash', 'sale_date' => '2025-01-10']));
        $batch = Production::withoutEvents(fn () => Production::create(['batch_id' => 'DBL-BATCH', 'product_id' => $product->id, 'quantity_produced' => 100, 'quantity_remaining' => 100, 'damaged' => 0, 'production_date' => '2025-01-05']));
        SaleItem::withoutEvents(fn () => SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'production_id' => $batch->id, 'quantity_sold' => 2, 'unit_price' => 2000, 'line_total' => 4000]));

        $this->actingAs($this->owner())->post(route('admin.reports.financial.store'), ['title' => 'Double count check', 'kind' => 'income_statement', 'mode' => 'actual', 'source' => 'system', 'start_date' => '2025-01-01', 'as_of' => '2025-01-31', 'prepared_by' => 'Manager'])->assertRedirect();
        $calc = app(FinancialReportService::class)->calculate(FinancialReport::firstOrFail());

        // 2 units at FIFO cost 50 = 100. Not 500 (purchase), not 600 (purchase + expense),
        // and not 1000 (the old purchases-plus-COGS-expenses double count).
        $this->assertEquals(100, $calc['totals']['costs'][0]);
        $this->assertEquals(3900, $calc['totals']['gross'][0]);
        // The exclusion must be disclosed, never silent.
        $this->assertStringContainsString('excluded to avoid double counting', implode(' ', FinancialReport::firstOrFail()->source_notes));
    }

    /**
     * Raw materials a batch consumes must reach cost of goods sold, line by line, in the
     * month of the batch. Output still unsold at month end is carried back out through the
     * finished goods stock line, so the net charge is the cost of what was actually sold.
     */
    public function test_raw_materials_consumed_in_production_reach_cost_of_goods_sold(): void
    {
        $product = Product::create(['name' => 'Neza Chilli Paste', 'type' => 'Neza Chilli Paste', 'barcode' => 'CONS-1', 'price' => 500]);
        $lot = InventoryRecord::withoutEvents(fn () => InventoryRecord::create(['supplier_name' => 'Green Leaf Cooperative', 'item_type' => 'Raw Material', 'item_name' => 'Fresh Red Chilli', 'quantity_in' => 100, 'quantity_out' => 40, 'damaged' => 0, 'storage_location' => 'Store', 'record_date' => '2025-01-02', 'unit_cost' => 1000, 'total_amount' => 100000]));
        // 40 kg at 1,000 = 40,000 for 200 jars, i.e. 200 per jar.
        $batch = Production::withoutEvents(fn () => Production::create(['batch_id' => 'CONS-BATCH', 'product_id' => $product->id, 'quantity_produced' => 200, 'quantity_remaining' => 150, 'damaged' => 0, 'production_date' => '2025-01-05', 'inventory_record_id' => [['inventory_id' => $lot->id, 'quantity_used' => 40]]]));
        $sale = Sale::withoutEvents(fn () => Sale::create(['sales_id' => 'CONS-SALE', 'customer_name' => 'Customer', 'total_revenue' => 25000, 'amount_paid' => 0, 'payment_status' => 'Credit', 'delivery_status' => 'Delivered', 'sales_channel' => 'Cash', 'sale_date' => '2025-01-20']));
        SaleItem::withoutEvents(fn () => SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'production_id' => $batch->id, 'quantity_sold' => 50, 'unit_price' => 500, 'line_total' => 25000]));

        $this->actingAs($this->owner())->post(route('admin.reports.financial.store'), ['title' => 'Consumption check', 'kind' => 'income_statement', 'mode' => 'actual', 'source' => 'system', 'start_date' => '2025-01-01', 'as_of' => '2025-01-31', 'prepared_by' => 'Manager'])->assertRedirect();
        $calc = app(FinancialReportService::class)->calculate(FinancialReport::firstOrFail());

        $consumed = collect($calc['lines'])->firstWhere('label', 'Fresh Red Chilli (raw materials consumed)');
        $this->assertNotNull($consumed, 'Raw materials consumed in production must appear under cost of goods sold.');
        $this->assertEquals(40000, $consumed['values'][0]);
        // 150 jars unsold at 200 each stay in stock.
        $stock = collect($calc['lines'])->firstWhere('label', 'Finished goods stock: opening less closing');
        $this->assertEquals(-30000, $stock['values'][0]);
        // Net: 50 jars sold at 200. Never the 100,000 purchased.
        $this->assertEquals(10000, $calc['totals']['costs'][0]);

        // The Expenses page reports the same consumption as COGS, not the purchase.
        $summary = app(\App\Services\ExpenseAnalyticsService::class)->yearSummary(2025);
        $this->assertEquals(40000, $summary['total_cogs']);
        $january = app(\App\Services\ExpenseAnalyticsService::class)->monthBreakdown(2025, 1, ExpenseCategory::TYPE_COGS);
        $this->assertSame('Fresh Red Chilli', $january['items'][0]['name']);
        $this->assertSame('production', $january['items'][0]['source']);
    }

    public function test_a_draft_report_can_be_deleted_from_the_list(): void
    {
        $report = $this->report();
        $owner = $this->owner();

        $this->actingAs($owner)->get(route('admin.reports.financial.index'))
            ->assertOk()->assertSee(route('admin.reports.financial.destroy', $report), false);
        $this->actingAs($owner)->delete(route('admin.reports.financial.destroy', $report))
            ->assertRedirect(route('admin.reports.financial.index'));
        $this->assertModelMissing($report);
    }

    /**
     * The monthly charts cover the twelve months up to the period, labelled with their
     * year. Grouping by month number alone used to add March 2024 to March 2025.
     */
    public function test_dashboard_monthly_charts_keep_years_apart(): void
    {
        foreach (['2024-03-10' => 700, '2025-03-10' => 1000] as $date => $amount) {
            Sale::withoutEvents(fn () => Sale::create(['sales_id' => 'YR-'.$date, 'customer_name' => 'Customer', 'total_revenue' => $amount, 'amount_paid' => 0, 'payment_status' => 'Credit', 'delivery_status' => 'Delivered', 'sales_channel' => 'Cash', 'sale_date' => $date]));
        }

        $data = app(\App\Services\AdminDashboardService::class)->build(null, '2025-01-01', '2025-12-31');

        $this->assertSame('Jan 25', $data['month_labels'][0]);
        $this->assertSame('Dec 25', $data['month_labels'][11]);
        $this->assertEquals(1000, $data['monthly_revenue'][2]);
        $this->assertEquals(1000, array_sum($data['monthly_revenue']));
    }

    /** The dashboard and the financial report must not disagree about what revenue is. */
    public function test_dashboard_and_financial_report_recognise_the_same_revenue(): void
    {
        foreach ([['Delivered', 'Paid', 1000], ['Delivered', 'Credit', 700], ['Pending', 'Paid', 400]] as $i => [$delivery, $payment, $amount]) {
            Sale::withoutEvents(fn () => Sale::create(['sales_id' => 'REV-'.$i, 'customer_name' => 'Customer', 'total_revenue' => $amount, 'amount_paid' => $payment === 'Paid' ? $amount : 0, 'payment_status' => $payment, 'delivery_status' => $delivery, 'sales_channel' => 'Cash', 'sale_date' => '2025-03-10']));
        }

        $dashboard = app(\App\Services\AdminDashboardService::class)
            ->build(null, '2025-03-01', '2025-03-31')['revenue_recognised'];
        $recognised = app(\App\Services\RevenueRecognitionService::class)
            ->recognisedRevenue('2025-03-01', '2025-03-31');

        // Delivered only, paid or not: 1000 + 700. The undelivered 400 is not yet earned.
        $this->assertEquals(1700, $recognised);
        $this->assertEquals($recognised, $dashboard);
    }

    public function test_monthly_annual_and_comparison_totals_and_missing_inputs(): void
    {
        $service = app(FinancialReportService::class);
        $report = $this->report();
        $calc = $service->calculate($report);
        $this->assertEquals(450, $calc['totals']['net'][0]);
        $this->assertEquals(5400, collect($calc['lines'])->firstWhere('key', 'net')['annual']);
        $prior = $this->report(['start_date' => '2024-01-01']);
        $this->assertEquals(0, $service->comparison($report, $prior)[0]['growth']);
        $rows = $report->rows;
        $rows[0]['values'][0] = null;
        $report->rows = $rows;
        $calc = $service->calculate($report);
        $this->assertNull($calc['totals']['net'][0]);
        $this->assertNull(collect($calc['lines'])->firstWhere('key', 'net')['annual']);
        $this->assertEquals(1, $calc['missing']);
    }

    public function test_forecast_rolls_may_april_and_keeps_future_annual_values_out_of_first_year_total(): void
    {
        $service = app(FinancialReportService::class);
        $report = $this->report(['kind' => 'financial_table', 'mode' => 'forecast', 'start_date' => '2026-05-01', 'rows' => [['section' => 'revenue', 'label' => 'Sales', 'values' => array_merge(array_fill(0, 12, 100), [9000, 10000, 11000])]]]);
        $columns = $service->columns($report);
        $this->assertSame('Apr 2027', $columns[11]);
        $this->assertSame('May 2027 - Apr 2028', $columns[12]);
        $calc = $service->calculate($report);
        $this->assertEquals(1200, collect($calc['lines'])->firstWhere('key', 'revenue')['annual']);
        $this->assertEquals(9000, $calc['totals']['net'][12]);
        $this->actingAs($this->owner())->post(route('admin.reports.financial.store'), ['title' => 'Future', 'kind' => 'financial_table', 'mode' => 'forecast', 'source' => 'manual', 'start_date' => '2028-05-01', 'as_of' => '2026-01-01', 'prepared_by' => 'Manager'])->assertRedirect();
    }

    public function test_edit_validation_revision_conflict_and_preview(): void
    {
        $report = $this->report();
        $this->actingAs($this->owner());
        $this->get(route('admin.reports.financial.index'))->assertOk();
        $this->get(route('admin.reports.financial.edit', $report))->assertOk()->assertSee('Save and preview');
        $payload = ['title' => 'Updated report', 'prepared_by' => 'Manager', 'version' => 1, 'rows_json' => json_encode($report->rows)];
        $this->put(route('admin.reports.financial.update', $report), $payload)->assertRedirect();
        $this->assertEquals(2, $report->fresh()->version);
        $this->get(route('admin.reports.financial.show', $report))->assertOk()->assertSee('5,400.00')->assertSee('Download Excel');
        $this->put(route('admin.reports.financial.update', $report), $payload)->assertStatus(409);
        $bad = $report->rows;
        $bad[0]['values'] = [10];
        $this->putJson(route('admin.reports.financial.update', $report), array_replace($payload, ['version' => 2, 'rows_json' => json_encode($bad)]))->assertUnprocessable();
        $bad[0]['values'] = array_fill(0, 12, 'invalid');
        $this->putJson(route('admin.reports.financial.update', $report), array_replace($payload, ['version' => 2, 'rows_json' => json_encode($bad)]))->assertUnprocessable();
    }

    public function test_pdf_and_excel_are_real_files_with_matching_figures_and_safe_text(): void
    {
        $report = $this->report(['title' => '=1+1']);
        $this->actingAs($this->owner());
        $pdf = $this->get(route('admin.reports.financial.export', [$report, 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->get(route('admin.reports.financial.export', [$report, 'pdf', 'preview' => 1]))->assertHeader('Content-Disposition', 'inline; filename="shaky-financial-report-'.$report->id.'-v1.pdf"');
        $response = $this->get(route('admin.reports.financial.export', [$report, 'xlsx']));
        $response->assertOk();
        $bytes = $response->streamedContent();
        $this->assertStringStartsWith('PK', $bytes);
        $path = tempnam(sys_get_temp_dir(), 'shaky-xlsx-');
        try {
            file_put_contents($path, $bytes);
            $sheet = IOFactory::load($path)->getActiveSheet();
            $this->assertSame('EXALTO GREEN LTD - =1+1', $sheet->getCell('A1')->getValue());
            $this->assertEquals(12000, $sheet->getCell('N8')->getCalculatedValue());
            $this->assertEquals(5400, $sheet->getCell('N20')->getCalculatedValue());
            $sheet->setCellValue('B7', 2000);
            \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($sheet->getParent())->clearCalculationCache();
            $this->assertEquals(6400, $sheet->getCell('N20')->getCalculatedValue());
        } finally {
            unlink($path);
        }
        $this->get(route('admin.reports.financial.export', [$report, 'csv']))->assertNotFound();
    }

    public function test_sales_staff_cannot_read_edit_or_export_financial_reports(): void
    {
        $report = $this->report();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SALES, 'is_active' => true]));
        $this->get(route('admin.reports.financial.index'))->assertForbidden();
        $this->get(route('admin.reports.financial.show', $report))->assertForbidden();
        $this->get(route('admin.reports.financial.export', [$report, 'pdf']))->assertForbidden();
        $this->put(route('admin.reports.financial.update', $report))->assertForbidden();
        $this->post(route('admin.reports.financial.store'))->assertForbidden();
    }
}
