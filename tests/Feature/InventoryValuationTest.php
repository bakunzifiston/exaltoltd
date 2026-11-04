<?php

namespace Tests\Feature;

use App\Models\FinancialReport;
use App\Models\InventoryRecord;
use App\Models\OpeningBalance;
use App\Models\Product;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stock on the balance sheet must move on the dates the business actually used it, and
 * every movement must reach the income statement, or the balance sheet cannot balance.
 */
class InventoryValuationTest extends TestCase
{
    use RefreshDatabase;

    private function lot(string $date, float $qty, float $unitCost, float $used = 0, float $damaged = 0): InventoryRecord
    {
        return InventoryRecord::withoutEvents(fn () => InventoryRecord::create([
            'supplier_name' => 'Green Leaf Cooperative', 'item_type' => 'Raw Material', 'item_name' => 'Fresh Red Chilli',
            'quantity_in' => $qty, 'quantity_out' => $used, 'damaged' => $damaged, 'storage_location' => 'Store',
            'record_date' => $date, 'unit_cost' => $unitCost, 'total_amount' => $qty * $unitCost,
        ]));
    }

    private function batch(string $date, InventoryRecord $lot, float $used, float $produced): Production
    {
        $product = Product::firstOrCreate(['type' => 'Neza Chilli Paste'], ['name' => 'Neza Chilli Paste', 'barcode' => 'VAL-1', 'price' => 600]);

        return Production::withoutEvents(fn () => Production::create([
            'batch_id' => 'VAL-'.$date, 'product_id' => $product->id, 'quantity_produced' => $produced,
            'quantity_remaining' => $produced, 'damaged' => 0, 'production_date' => $date,
            'inventory_record_id' => [['inventory_id' => $lot->id, 'quantity_used' => $used]],
        ]));
    }

    private function sell(string $date, Production $batch, float $qty, float $total): void
    {
        $sale = Sale::withoutEvents(fn () => Sale::create(['sales_id' => 'VAL-SALE-'.$date, 'customer_name' => 'Customer', 'total_revenue' => $total, 'amount_paid' => 0, 'payment_status' => 'Credit', 'delivery_status' => 'Delivered', 'sales_channel' => 'Cash', 'sale_date' => $date]));
        SaleItem::withoutEvents(fn () => SaleItem::create(['sale_id' => $sale->id, 'product_id' => $batch->product_id, 'production_id' => $batch->id, 'quantity_sold' => $qty, 'unit_price' => $total / $qty, 'line_total' => $total]));
    }

    private function calculate(string $kind): array
    {
        $service = app(FinancialReportService::class);
        $report = new FinancialReport(['title' => $kind, 'kind' => $kind, 'mode' => 'actual', 'source' => 'system', 'start_date' => '2025-01-01', 'as_of' => '2025-12-31', 'prepared_by' => 'Finance']);
        [$rows, $notes] = $service->snapshot($report);
        $report->fill(['rows' => $rows, 'source_notes' => $notes])->save();

        return $service->calculate($report->refresh());
    }

    private function line(array $calc, string $label): array
    {
        $line = collect($calc['lines'])->firstWhere('label', $label);
        $this->assertNotNull($line, 'Missing line: '.$label);

        return $line['values'];
    }

    private function assertBalances(array $balanceSheet): void
    {
        $this->assertSame([], $balanceSheet['checks'], 'Balance sheet did not balance: '.json_encode($balanceSheet['checks']));
    }

    public function test_raw_stock_is_relieved_when_the_batch_uses_it_not_when_it_was_bought(): void
    {
        $lot = $this->lot('2025-01-05', 100, 1000, used: 40);
        $batch = $this->batch('2025-03-10', $lot, 40, 200);
        $this->sell('2025-04-15', $batch, 50, 30000);

        $balance = $this->calculate(FinancialReport::KIND_BALANCE_SHEET);
        $this->assertBalances($balance);

        // The 40 kg is still raw stock in January and February; it only becomes finished
        // goods in March. Relieving it on the purchase date understated both months.
        $this->assertEquals([100000, 100000, 100000, 90000], array_slice($this->line($balance, 'Inventory'), 0, 4));

        $income = $this->calculate(FinancialReport::KIND_INCOME_STATEMENT);
        // March: 40,000 used, all still on the shelf. April: 50 jars sold at 200 each.
        $this->assertEquals([0, 0, 0, 10000], array_slice($income['totals']['costs'], 0, 4));
    }

    public function test_opening_inventory_is_not_counted_twice(): void
    {
        OpeningBalance::create(['as_of_date' => '2024-12-31', 'line_key' => OpeningBalance::INVENTORY, 'amount' => 100000]);
        OpeningBalance::create(['as_of_date' => '2024-12-31', 'line_key' => OpeningBalance::SHARE_CAPITAL, 'amount' => 100000]);
        // Bought before the cut-over, so it is inside the opening inventory figure.
        $lot = $this->lot('2024-12-20', 100, 1000, used: 40);
        $batch = $this->batch('2025-02-10', $lot, 40, 200);
        $this->sell('2025-03-05', $batch, 50, 30000);

        $balance = $this->calculate(FinancialReport::KIND_BALANCE_SHEET);
        $this->assertBalances($balance);
        $this->assertEquals([100000, 100000, 90000], array_slice($this->line($balance, 'Inventory'), 0, 3));
    }

    public function test_a_batch_cost_that_does_not_divide_evenly_leaves_no_residue(): void
    {
        $lot = $this->lot('2025-01-02', 10, 1000, used: 10);
        // 10,000 over 3,000 units is 3.333... per unit.
        $this->batch('2025-01-05', $lot, 10, 3000);

        $income = $this->calculate(FinancialReport::KIND_INCOME_STATEMENT);
        $this->assertEquals(-10000, $this->line($income, 'Finished goods stock: opening less closing')[0]);
        $this->assertEquals(0, $income['totals']['costs'][0]);
        $this->assertBalances($this->calculate(FinancialReport::KIND_BALANCE_SHEET));
    }

    public function test_damaged_raw_materials_are_charged_and_leave_stock(): void
    {
        $this->lot('2025-01-05', 100, 1000, damaged: 5);

        $income = $this->calculate(FinancialReport::KIND_INCOME_STATEMENT);
        $this->assertEquals(5000, $this->line($income, 'Raw materials damaged or issued outside batches')[0]);

        $balance = $this->calculate(FinancialReport::KIND_BALANCE_SHEET);
        $this->assertBalances($balance);
        $this->assertEquals(95000, $this->line($balance, 'Inventory')[0]);
    }

    public function test_the_first_and_last_day_of_a_period_are_both_included(): void
    {
        $lot = $this->lot('2025-01-01', 100, 1000, used: 10);
        $batch = $this->batch('2025-01-01', $lot, 10, 100);
        // Sales on the very first and very last day of the window.
        $this->sell('2025-01-01', $batch, 10, 5000);
        $this->sell('2025-12-31', $batch, 10, 5000);

        // Recalculating costs over all sales used to skip every line on the first day.
        $result = app(\App\Services\SaleCostingService::class)->recost();
        $this->assertSame(2, $result['costed_from_batch']);
        $this->assertSame(0, SaleItem::whereNull('line_cogs')->count());
    }

    /**
     * An asset bought before the cut-over is inside the opening figures, but the income
     * statement still depreciates it every month. The balance sheet has to carry that
     * depreciation too, or it drifts out of balance by one month's charge each month.
     */
    public function test_assets_held_before_the_cut_over_keep_the_books_balanced(): void
    {
        foreach ([OpeningBalance::FIXED_ASSETS_COST => 120000, OpeningBalance::SHARE_CAPITAL => 120000] as $line => $amount) {
            OpeningBalance::create(['as_of_date' => '2024-12-31', 'line_key' => $line, 'amount' => $amount]);
        }
        \App\Models\FixedAsset::create(['name' => 'Pasteurizer', 'acquisition_date' => '2024-06-01', 'cost' => 120000, 'salvage_value' => 0, 'useful_life_months' => 120]);

        $balance = $this->calculate(FinancialReport::KIND_BALANCE_SHEET);
        $this->assertBalances($balance);
        // 1,000 a month charged since the cut-over, on top of the opening position.
        $this->assertEquals(-1000, $this->line($balance, 'Less accumulated depreciation')[0]);
    }
}
