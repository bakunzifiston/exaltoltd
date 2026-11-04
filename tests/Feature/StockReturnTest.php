<?php

namespace Tests\Feature;

use App\Models\FinancialReport;
use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\StockReturn;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Services\RevenueRecognitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A supermarket took 100 jars on credit, sold 40 and paid for them, and sends the other
 * 60 back: 50 still fine, 10 damaged.
 */
class StockReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $seller;

    private Production $batch;

    private Sale $sale;

    private SaleItem $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);
        $this->seller = User::factory()->create(['role' => User::ROLE_SALES, 'is_active' => true]);
        $product = Product::create(['name' => 'Neza Chilli Paste', 'type' => 'Neza Chilli Paste', 'barcode' => 'RET-1', 'price' => 500]);
        $lot = InventoryRecord::withoutEvents(fn () => InventoryRecord::create(['supplier_name' => 'Green Leaf', 'item_type' => 'Raw Material', 'item_name' => 'Fresh Red Chilli', 'quantity_in' => 100, 'quantity_out' => 40, 'damaged' => 0, 'storage_location' => 'Store', 'record_date' => '2025-01-02', 'unit_cost' => 1000, 'total_amount' => 100000]));
        // 40,000 of chilli for 200 jars: 200 per jar.
        $this->batch = Production::withoutEvents(fn () => Production::create(['batch_id' => 'RET-BATCH', 'product_id' => $product->id, 'quantity_produced' => 200, 'quantity_remaining' => 100, 'damaged' => 0, 'production_date' => '2025-01-05', 'inventory_record_id' => [['inventory_id' => $lot->id, 'quantity_used' => 40]]]));
        $this->sale = Sale::withoutEvents(fn () => Sale::create(['sales_id' => 'RET-SALE', 'customer_name' => 'Simba Supermarket', 'total_revenue' => 50000, 'amount_paid' => 20000, 'payment_status' => 'Partially Paid', 'delivery_status' => 'Delivered', 'sales_channel' => 'Cash', 'sale_date' => '2025-01-10', 'created_by' => $this->seller->id]));
        $this->line = SaleItem::withoutEvents(fn () => SaleItem::create(['sale_id' => $this->sale->id, 'product_id' => $product->id, 'production_id' => $this->batch->id, 'quantity_sold' => 100, 'unit_price' => 500, 'line_total' => 50000]));
        SalePayment::create(['sale_id' => $this->sale->id, 'amount' => 20000, 'method' => 'Cash', 'paid_at' => '2025-01-25']);
    }

    private function submitReturn(array $line, ?User $as = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->seller)->post(route('admin.stock-returns.store'), [
            'sale_id' => $this->sale->id, 'return_date' => '2025-02-15', 'reason' => 'payment', 'action' => 'submit',
            'lines' => [['sale_item_id' => $this->line->id] + $line],
        ]);
    }

    private function approve(StockReturn $return, array $split): \Illuminate\Testing\TestResponse
    {
        $item = $return->items()->firstOrFail();

        return $this->actingAs($this->owner)->post(route('admin.stock-returns.approve', $return), ['inspection' => [$item->id => $split]]);
    }

    public function test_a_partial_return_is_inspected_restocks_only_sellable_units_and_credits_the_order(): void
    {
        $this->submitReturn(['quantity_sold_reported' => 40, 'quantity_returned' => 60])->assertSessionHasNoErrors();
        $return = StockReturn::firstOrFail();

        $this->assertSame(StockReturn::PENDING_INSPECTION, $return->status);
        $this->assertMatchesRegularExpression('/^SR-\d{4}-\d{5}$/', $return->reference);
        $this->assertEquals(30000, $return->total_value);
        // Nothing moves before inspection.
        $this->assertEquals(100, $this->batch->fresh()->quantity_remaining);
        $this->assertEquals(0, $this->sale->fresh()->returned_amount);

        $this->approve($return, ['quantity_restocked' => 50, 'quantity_damaged' => 10])->assertSessionHasNoErrors();

        $this->assertSame(StockReturn::APPROVED, $return->fresh()->status);
        $this->assertEquals(150, $this->batch->fresh()->quantity_remaining, 'Only the 50 sellable jars go back on the shelf.');
        $this->assertEquals(60, $this->line->fresh()->quantity_returned);

        $movements = StockMovement::where('reference_type', 'stock_return')->pluck('quantity', 'type')->map(fn ($q) => (float) $q)->all();
        $this->assertEquals(['return_restock' => 50.0, 'return_received' => 10.0, 'return_written_off' => -10.0], $movements);

        // 50,000 invoiced - 30,000 returned = 20,000 owed, and 20,000 was paid.
        $sale = $this->sale->fresh();
        $this->assertEquals(30000, $sale->returned_amount);
        $this->assertEquals(0, $sale->balance_due);
        $this->assertSame('Paid', $sale->payment_status);
        $this->assertSame(1, SalePayment::count(), 'No payment is invented by a return.');
        $this->assertSame(1, Sale::count(), 'No second sale is created by a return.');
    }

    public function test_returns_cannot_exceed_what_is_still_outstanding(): void
    {
        $this->submitReturn(['quantity_returned' => 101])->assertSessionHasErrors('lines.0.quantity_returned');
        $this->submitReturn(['quantity_sold_reported' => 60, 'quantity_returned' => 50])->assertSessionHasErrors('lines.0.quantity_sold_reported');

        $this->submitReturn(['quantity_returned' => 60])->assertSessionHasNoErrors();
        // 60 are already on a return in progress, so only 40 more can come back.
        $this->submitReturn(['quantity_returned' => 41])->assertSessionHasErrors('lines.0.quantity_returned');
        $this->submitReturn(['quantity_returned' => 40])->assertSessionHasNoErrors();
    }

    public function test_approving_twice_does_not_post_twice(): void
    {
        $this->submitReturn(['quantity_returned' => 20]);
        $return = StockReturn::firstOrFail();

        $this->approve($return, ['quantity_restocked' => 20])->assertSessionHasNoErrors();
        $this->approve($return, ['quantity_restocked' => 20])->assertSessionHasErrors('status');

        $this->assertEquals(120, $this->batch->fresh()->quantity_remaining);
        $this->assertEquals(10000, $this->sale->fresh()->returned_amount);
    }

    public function test_the_inspection_must_account_for_every_unit_returned(): void
    {
        $this->submitReturn(['quantity_returned' => 20]);

        $this->approve(StockReturn::firstOrFail(), ['quantity_restocked' => 15])->assertSessionHasErrors();
        $this->assertSame(StockReturn::PENDING_INSPECTION, StockReturn::firstOrFail()->status);
    }

    public function test_a_rejected_return_changes_nothing_and_frees_the_quantity(): void
    {
        $this->submitReturn(['quantity_returned' => 60]);
        $return = StockReturn::firstOrFail();

        $this->actingAs($this->owner)->post(route('admin.stock-returns.reject', $return), ['inspection_notes' => 'Goods were never collected'])->assertSessionHasNoErrors();

        $this->assertSame(StockReturn::REJECTED, $return->fresh()->status);
        $this->assertEquals(100, $this->batch->fresh()->quantity_remaining);
        $this->assertEquals(0, $this->sale->fresh()->returned_amount);
        $this->submitReturn(['quantity_returned' => 100])->assertSessionHasNoErrors();
    }

    public function test_sales_staff_record_returns_but_cannot_approve_them_or_see_others(): void
    {
        $this->submitReturn(['quantity_returned' => 10])->assertSessionHasNoErrors();
        $return = StockReturn::firstOrFail();

        $this->actingAs($this->seller)->post(route('admin.stock-returns.approve', $return), ['inspection' => []])->assertForbidden();

        $other = User::factory()->create(['role' => User::ROLE_SALES, 'is_active' => true]);
        $this->actingAs($other)->get(route('admin.stock-returns.show', $return))->assertForbidden();
        $this->actingAs($other)->get(route('admin.stock-returns.index'))->assertOk()->assertDontSee($return->reference);
    }

    public function test_an_order_with_returns_keeps_its_lines_and_cannot_be_deleted(): void
    {
        $this->submitReturn(['quantity_returned' => 10]);

        $this->actingAs($this->owner)->put(route('admin.sales.update', $this->sale), [
            'customer_name' => 'Simba Supermarket', 'sale_date' => '2025-01-10', 'payment_status' => 'Credit',
            'delivery_status' => 'Delivered', 'sales_channel' => 'Cash',
            'items' => [['product_id' => $this->line->product_id, 'production_id' => $this->batch->id, 'quantity_sold' => 90, 'unit_price' => 500]],
        ])->assertSessionHasErrors('items');

        $this->actingAs($this->owner)->delete(route('admin.sales.destroy', $this->sale))->assertSessionHasErrors('sale');
        $this->assertModelExists($this->sale);
    }

    public function test_statements_take_the_credit_and_the_restocked_stock_on_the_return_date_and_still_balance(): void
    {
        $this->submitReturn(['quantity_sold_reported' => 40, 'quantity_returned' => 60]);
        $this->approve(StockReturn::firstOrFail(), ['quantity_restocked' => 50, 'quantity_damaged' => 10]);

        $service = app(FinancialReportService::class);
        $calc = function (string $kind) use ($service) {
            $report = new FinancialReport(['title' => $kind, 'kind' => $kind, 'mode' => 'actual', 'source' => 'system', 'start_date' => '2025-01-01', 'as_of' => '2025-12-31', 'prepared_by' => 'Finance']);
            [$rows, $notes] = $service->snapshot($report);
            $report->fill(['rows' => $rows, 'source_notes' => $notes])->save();

            return $service->calculate($report->refresh());
        };

        $income = $calc(FinancialReport::KIND_INCOME_STATEMENT);
        // January: 50,000 sold. February: 30,000 credited.
        $this->assertEquals([50000, -30000], array_slice($income['totals']['revenue'], 0, 2));
        // February: 50 jars back on the shelf at 200 each reduce cost of goods; the 10
        // damaged jars stay a cost.
        $this->assertEquals(-10000, $income['totals']['costs'][1]);

        $balance = $calc(FinancialReport::KIND_BALANCE_SHEET);
        $this->assertSame([], $balance['checks'], 'Balance sheet did not balance: '.json_encode($balance['checks']));

        $this->assertEquals(20000, app(RevenueRecognitionService::class)->recognisedRevenue('2025-01-01', '2025-12-31'));
    }

    public function test_documents_are_attached_privately_and_every_step_is_in_the_history(): void
    {
        Storage::fake('local');
        $this->actingAs($this->seller)->post(route('admin.stock-returns.store'), [
            'sale_id' => $this->sale->id, 'return_date' => '2025-02-15', 'reason' => 'other', 'reason_other' => 'Shop closing down',
            'action' => 'draft', 'lines' => [['sale_item_id' => $this->line->id, 'quantity_returned' => 5]],
            'documents' => [UploadedFile::fake()->create('delivery-note.pdf', 120, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $return = StockReturn::firstOrFail();
        $this->assertSame('Other: Shop closing down', $return->reasonLabel());

        $document = $return->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->path);
        $this->actingAs($this->seller)->get(route('admin.stock-returns.documents.show', [$return, $document]))->assertOk();

        $this->actingAs($this->seller)->post(route('admin.stock-returns.submit', $return))->assertSessionHasNoErrors();
        $this->approve($return, ['quantity_restocked' => 5]);

        $events = $return->events()->get()->pluck('event')->all();
        $this->assertContains('created', $events);
        $this->assertContains('document_added', $events);
        $this->assertSame(2, $return->events()->where('event', 'status_changed')->count());

        $this->actingAs($this->owner)->get(route('admin.stock-returns.show', $return))->assertOk()->assertSee('Shop closing down')->assertSee('delivery-note.pdf');
    }

    public function test_return_screens_and_report_render(): void
    {
        $this->submitReturn(['quantity_returned' => 10]);
        $this->approve(StockReturn::firstOrFail(), ['quantity_restocked' => 8, 'quantity_expired' => 2]);

        $this->actingAs($this->owner);
        $this->get(route('admin.stock-returns.index', ['status' => 'approved', 'search' => 'Simba']))->assertOk()->assertSee('Simba Supermarket');
        $this->get(route('admin.stock-returns.create', ['customer_id' => $this->sale->customer_id, 'sale_id' => $this->sale->id]))->assertOk();
        $this->get(route('admin.sales.show', $this->sale))->assertOk()->assertSee(StockReturn::firstOrFail()->reference);
        foreach (['returns', 'customer-balances', 'stock-movements'] as $type) {
            $this->get(route('admin.reports.operational.show', ['type' => $type, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31']))->assertOk();
            $this->assertStringStartsWith('PK', $this->get(route('admin.reports.operational.export', ['type' => $type, 'start_date' => '2025-01-01', 'end_date' => '2025-12-31']))->streamedContent());
        }
    }
}
