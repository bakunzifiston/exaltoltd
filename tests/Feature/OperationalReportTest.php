<?php

namespace Tests\Feature;

use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\OperationalReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class OperationalReportTest extends TestCase
{
    use RefreshDatabase;

    private const PERIOD = ['start_date' => '2025-01-01', 'end_date' => '2025-12-31'];

    protected function setUp(): void
    {
        parent::setUp();

        $product = Product::create(['name' => 'Neza Chilli Paste', 'type' => 'Neza Chilli Paste', 'barcode' => 'OPS-1', 'price' => 500]);
        $chilli = InventoryRecord::withoutEvents(fn () => InventoryRecord::create(['supplier_name' => 'Green Leaf Cooperative', 'item_type' => 'Raw Material', 'item_name' => 'Fresh Red Chilli', 'quantity_in' => 100, 'quantity_out' => 40, 'damaged' => 0, 'storage_location' => 'Store', 'record_date' => '2025-01-02', 'unit_cost' => 1000, 'total_amount' => 100000, 'amount_paid' => 60000, 'payment_status' => 'Partial']));
        $jars = InventoryRecord::withoutEvents(fn () => InventoryRecord::create(['supplier_name' => 'Rwanda Plastic Industries Ltd', 'item_type' => 'Raw Material', 'item_name' => 'PET Jar 120g', 'quantity_in' => 500, 'quantity_out' => 200, 'damaged' => 0, 'storage_location' => 'Store', 'record_date' => '2025-01-03', 'unit_cost' => 50, 'total_amount' => 25000, 'amount_paid' => 25000, 'payment_status' => 'Paid']));
        // 40 kg chilli (40,000) + 200 jars (10,000) = 50,000 for 200 jars: 250 per jar.
        $batch = Production::withoutEvents(fn () => Production::create(['batch_id' => 'OPS-BATCH', 'product_id' => $product->id, 'quantity_produced' => 200, 'quantity_remaining' => 150, 'damaged' => 4, 'production_date' => '2025-01-05', 'responsible_staff' => 'Aline', 'inventory_record_id' => [['inventory_id' => $chilli->id, 'quantity_used' => 40], ['inventory_id' => $jars->id, 'quantity_used' => 200]]]));
        $sale = Sale::withoutEvents(fn () => Sale::create(['sales_id' => 'OPS-SALE', 'customer_name' => 'Simba Supermarket', 'total_revenue' => 25000, 'amount_paid' => 10000, 'payment_status' => 'Partially Paid', 'delivery_status' => 'Delivered', 'sales_channel' => 'Retail', 'sale_date' => '2025-01-20']));
        SaleItem::withoutEvents(fn () => SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'production_id' => $batch->id, 'quantity_sold' => 50, 'unit_price' => 500, 'line_total' => 25000]));
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);
    }

    private function build(string $type): array
    {
        return app(OperationalReportService::class)->build($type, CarbonImmutable::parse('2025-01-01'), CarbonImmutable::parse('2025-12-31'));
    }

    private function headline(array $report, string $key): mixed
    {
        return collect($report['headline'])->firstWhere('key', $key)['value'];
    }

    public function test_raw_material_report_separates_purchases_used_and_in_stock(): void
    {
        $report = $this->build('raw-materials');

        $this->assertEquals(125000, $this->headline($report, 'purchases'));
        // Cost of goods is what production drew from stock, not what was bought.
        $this->assertEquals(50000, $this->headline($report, 'consumed'));
        $this->assertEquals(75000, $this->headline($report, 'stock'));
        $this->assertEquals(40000, $this->headline($report, 'outstanding'));

        $byItem = collect($report['tables'])->firstWhere('title', 'By item');
        $this->assertSame('Fresh Red Chilli', $byItem['lines'][0]['label']);
        $this->assertEquals(40000, $byItem['lines'][0]['values']['used']);
        $this->assertEquals(125000, $byItem['total']['purchases']);
    }

    public function test_sales_report_costs_lines_from_their_batch(): void
    {
        $report = $this->build('sales');

        $this->assertEquals(25000, $this->headline($report, 'delivered'));
        $this->assertEquals(15000, $this->headline($report, 'balance'));
        // 50 jars at 250 each.
        $this->assertEquals(12500, $this->headline($report, 'cogs'));
        $this->assertEqualsWithDelta(0.5, $this->headline($report, 'margin'), 0.0001);
    }

    public function test_production_report_summarises_batches_and_unit_cost(): void
    {
        $report = $this->build('production');

        $this->assertSame(1, $this->headline($report, 'batches'));
        $this->assertEquals(200, $this->headline($report, 'produced'));
        $this->assertEqualsWithDelta(0.02, $this->headline($report, 'damage_rate'), 0.0001);
        $this->assertEquals(50, $this->headline($report, 'sold'));
        $this->assertEquals(50000, $this->headline($report, 'cost'));
        $this->assertEquals(250, $this->headline($report, 'avg_unit'));
    }

    public function test_every_report_previews_and_downloads_as_a_workbook(): void
    {
        $owner = $this->owner();

        foreach (array_keys(OperationalReportService::TYPES) as $type) {
            $this->actingAs($owner)->get(route('admin.reports.operational.show', ['type' => $type] + self::PERIOD))
                ->assertOk()->assertSee('Download Excel');

            $response = $this->actingAs($owner)->get(route('admin.reports.operational.export', ['type' => $type] + self::PERIOD));
            $response->assertOk();
            $path = tempnam(sys_get_temp_dir(), 'ops').'.xlsx';
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::load($path);
            unlink($path);

            $this->assertSame('Summary', $book->getSheet(0)->getTitle());
            $this->assertSame($this->build($type)['title'], $book->getSheet(0)->getCell('B2')->getValue());
            $this->assertGreaterThan(1, $book->getSheetCount());
        }
    }

    public function test_sales_staff_cannot_open_operational_reports(): void
    {
        $sales = User::factory()->create(['role' => User::ROLE_SALES, 'is_active' => true]);

        $this->actingAs($sales)->get(route('admin.reports.operational.show', 'sales'))->assertForbidden();
        $this->actingAs($sales)->get(route('admin.reports.operational.export', 'sales'))->assertForbidden();
    }
}
