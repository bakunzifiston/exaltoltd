<?php

namespace Tests\Feature;

use App\Models\FinancialReport;
use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionSample;
use App\Models\Sale;
use App\Models\SiteSetting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\FinancialReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSampleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $manager;

    private User $worker;

    private Product $product;

    private Production $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);
        $this->manager = User::factory()->create(['role' => User::ROLE_MANAGER, 'is_active' => true]);
        $this->worker = User::factory()->create(['role' => User::ROLE_PRODUCTION, 'is_active' => true]);
        $this->product = Product::create(['name' => 'Neza Chilli Oil', 'type' => 'Neza Chilli Oil', 'barcode' => 'SMP-1', 'price' => 2000]);
        $lot = InventoryRecord::withoutEvents(fn () => InventoryRecord::create(['supplier_name' => 'Simba', 'item_type' => 'Raw Material', 'item_name' => 'Sunflower Oil', 'quantity_in' => 100, 'quantity_out' => 50, 'damaged' => 0, 'storage_location' => 'Store', 'record_date' => '2025-01-02', 'unit_cost' => 3000, 'total_amount' => 300000]));
        // 150,000 of oil for 300 bottles: 500 per bottle.
        $this->batch = Production::withoutEvents(fn () => Production::create(['batch_id' => 'SMP-BATCH', 'product_id' => $this->product->id, 'quantity_produced' => 300, 'quantity_remaining' => 300, 'damaged' => 0, 'production_date' => '2025-01-05', 'inventory_record_id' => [['inventory_id' => $lot->id, 'quantity_used' => 50]]]));
    }

    private function request(User $as, float $quantity, string $action = 'request'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as)->post(route('admin.production-samples.store'), [
            'product_id' => $this->product->id, 'production_id' => $this->batch->id, 'quantity' => $quantity,
            'sample_date' => '2025-02-03', 'recipient_type' => 'supermarket', 'recipient_name' => 'Simba Kimihurura',
            'purpose' => 'Tasting / in-store promotion', 'received_by_name' => 'Store manager', 'action' => $action,
        ]);
    }

    public function test_a_sample_is_requested_then_approved_leaving_stock_at_cost_without_a_sale(): void
    {
        $this->request($this->worker, 12)->assertSessionHasNoErrors();
        $sample = ProductionSample::firstOrFail();

        $this->assertSame(ProductionSample::PENDING, $sample->status);
        $this->assertMatchesRegularExpression('/^SMP-\d{4}-\d{5}$/', $sample->reference);
        $this->assertEquals(300, $this->batch->fresh()->quantity_remaining);

        $this->actingAs($this->worker)->post(route('admin.production-samples.approve', $sample))->assertForbidden();
        $this->actingAs($this->manager)->post(route('admin.production-samples.approve', $sample))->assertSessionHasNoErrors();

        $sample->refresh();
        $this->assertSame(ProductionSample::APPROVED, $sample->status);
        $this->assertSame($this->manager->id, $sample->approved_by);
        $this->assertEquals(6000, $sample->cost_value);
        $this->assertEquals(288, $this->batch->fresh()->quantity_remaining);
        $this->assertEquals(-12, StockMovement::where('type', 'sample')->value('quantity'));
        $this->assertSame(0, Sale::count(), 'A sample must never become a sale.');
    }

    public function test_requests_above_the_limit_need_the_owner(): void
    {
        SiteSetting::setValue('sample_approval_limit', '10');

        $this->request($this->manager, 25, 'approve')->assertSessionHasNoErrors();
        $sample = ProductionSample::firstOrFail();
        $this->assertSame(ProductionSample::PENDING, $sample->status, 'A manager cannot approve above the limit, even when recording it.');

        $this->actingAs($this->manager)->post(route('admin.production-samples.approve', $sample))->assertSessionHasErrors('status');
        $this->actingAs($this->owner)->post(route('admin.production-samples.approve', $sample))->assertSessionHasNoErrors();
        $this->assertSame(ProductionSample::APPROVED, $sample->fresh()->status);
    }

    public function test_samples_only_come_from_releasable_stock_that_exists(): void
    {
        $this->request($this->worker, 301)->assertSessionHasErrors('quantity');

        $this->batch->forceFill(['qc_status' => Production::QC_ON_HOLD])->saveQuietly();
        $this->request($this->worker, 5)->assertSessionHasErrors('production_id');
        $this->assertSame(0, ProductionSample::count());
    }

    public function test_the_cost_is_a_promotion_expense_not_cost_of_goods_and_the_books_balance(): void
    {
        $this->request($this->owner, 12, 'approve')->assertSessionHasNoErrors();

        $service = app(FinancialReportService::class);
        $calc = function (string $kind) use ($service) {
            $report = new FinancialReport(['title' => $kind, 'kind' => $kind, 'mode' => 'actual', 'source' => 'system', 'start_date' => '2025-01-01', 'as_of' => '2025-12-31', 'prepared_by' => 'Finance']);
            [$rows, $notes] = $service->snapshot($report);
            $report->fill(['rows' => $rows, 'source_notes' => $notes])->save();

            return $service->calculate($report->refresh());
        };

        $income = $calc(FinancialReport::KIND_INCOME_STATEMENT);
        $this->assertEquals(0, $income['totals']['revenue'][1]);
        $this->assertEquals(0, $income['totals']['costs'][1], 'Samples do not depress the gross margin.');
        $expense = collect($income['lines'])->firstWhere('label', 'Product samples given away (at cost)');
        $this->assertEquals(6000, $expense['values'][1]);

        $balance = $calc(FinancialReport::KIND_BALANCE_SHEET);
        $this->assertSame([], $balance['checks'], 'Balance sheet did not balance: '.json_encode($balance['checks']));
    }

    public function test_reversing_an_approved_sample_puts_the_units_back(): void
    {
        $this->request($this->owner, 12, 'approve');
        $sample = ProductionSample::firstOrFail();

        $this->actingAs($this->worker)->post(route('admin.production-samples.cancel', $sample), ['decision_notes' => 'Never collected'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('admin.production-samples.cancel', $sample), ['decision_notes' => 'Never collected'])->assertSessionHasNoErrors();

        $this->assertSame(ProductionSample::CANCELLED, $sample->fresh()->status);
        $this->assertEquals(300, $this->batch->fresh()->quantity_remaining);
        $this->assertEquals(12, StockMovement::where('type', 'sample_reversal')->value('quantity'));
    }

    public function test_samples_appear_in_the_batch_trace_and_their_screens_and_report_render(): void
    {
        $this->request($this->owner, 12, 'approve');
        $sample = ProductionSample::firstOrFail();

        $this->actingAs($this->owner);
        $this->get(route('admin.productions.show', ['production' => $this->batch, 'tab' => 'traceability']))->assertOk()->assertSee('Simba Kimihurura')->assertSee('Free sample');
        $this->get(route('admin.production-samples.index', ['search' => 'Simba']))->assertOk()->assertSee($sample->reference);
        $this->get(route('admin.production-samples.show', $sample))->assertOk()->assertSee('6,000 RWF');
        $this->get(route('admin.production-samples.create'))->assertOk();
        $this->get(route('admin.reports.operational.show', ['type' => 'samples', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31']))->assertOk()->assertSee('Simba Kimihurura');
    }

    public function test_staff_see_only_their_own_requests(): void
    {
        $this->request($this->worker, 3);
        $sample = ProductionSample::firstOrFail();
        $other = User::factory()->create(['role' => User::ROLE_PRODUCTION, 'is_active' => true]);

        $this->actingAs($other)->get(route('admin.production-samples.show', $sample))->assertForbidden();
        $this->actingAs($this->worker)->get(route('admin.production-samples.show', $sample))->assertOk();
    }

    public function test_a_sample_can_be_deleted_only_once_it_has_no_stock_effect(): void
    {
        $this->request($this->worker, 12);
        $sample = ProductionSample::firstOrFail();
        $this->actingAs($this->manager)->post(route('admin.production-samples.approve', $sample));
        $this->assertEquals(288, $this->batch->fresh()->quantity_remaining);

        // Approved: refused, stock and record untouched. Production staff may not delete at all.
        $this->actingAs($this->worker)->delete(route('admin.production-samples.destroy', $sample))->assertForbidden();
        $this->actingAs($this->manager)->delete(route('admin.production-samples.destroy', $sample))->assertSessionHasErrors('sample');
        $this->assertDatabaseHas('production_samples', ['id' => $sample->id]);
        $this->assertEquals(288, $this->batch->fresh()->quantity_remaining);

        // Reverse it (units return to the batch), then it can be deleted.
        $this->actingAs($this->manager)->post(route('admin.production-samples.cancel', $sample), ['decision_notes' => 'Entered by mistake']);
        $this->assertEquals(300, $this->batch->fresh()->quantity_remaining);
        $this->actingAs($this->manager)->delete(route('admin.production-samples.destroy', $sample))
            ->assertRedirect(route('admin.production-samples.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('production_samples', ['id' => $sample->id]);
        $this->assertEquals(300, $this->batch->fresh()->quantity_remaining);
    }

    public function test_bulk_delete_skips_approved_samples_and_removes_the_rest(): void
    {
        $this->request($this->worker, 5);
        $this->request($this->worker, 6);
        [$pending, $toApprove] = ProductionSample::orderBy('id')->get()->all();
        $this->actingAs($this->manager)->post(route('admin.production-samples.approve', $toApprove));

        $this->actingAs($this->manager)->post(route('admin.bulk-delete', 'production-samples'), ['ids' => [$pending->id, $toApprove->id]]);

        $result = session('bulk_result');
        $this->assertSame(1, $result['deleted']);
        $this->assertCount(1, $result['skipped']);
        $this->assertDatabaseMissing('production_samples', ['id' => $pending->id]);
        $this->assertDatabaseHas('production_samples', ['id' => $toApprove->id]);
    }
}
