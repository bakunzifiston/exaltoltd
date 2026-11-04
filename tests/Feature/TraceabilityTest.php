<?php

namespace Tests\Feature;

use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\StorefrontCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TraceabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Product $product;

    private InventoryRecord $chilli;

    private Production $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);
        $this->product = Product::create(['name' => 'Neza Chilli Paste', 'type' => 'Neza Chilli Paste', 'barcode' => 'TR-1', 'price' => 500, 'shelf_life_days' => 180]);
        $this->chilli = InventoryRecord::withoutEvents(fn () => InventoryRecord::create([
            'supplier_name' => 'Green Leaf Cooperative', 'item_type' => 'Raw Material', 'item_name' => 'Fresh Red Chilli',
            'supplier_lot_number' => 'GLC-2025-117', 'quantity_in' => 100, 'quantity_out' => 40, 'damaged' => 0,
            'storage_location' => 'Cold room', 'record_date' => '2025-01-02', 'unit_cost' => 1000, 'total_amount' => 100000,
        ]));
        $this->batch = $this->makeBatch('SHK/BN/201', '2025-01-05', 200, 150);

        $sale = Sale::withoutEvents(fn () => Sale::create(['sales_id' => 'TR-SALE-1', 'customer_name' => 'Simba Supermarket', 'customer_Phone' => '+250788111222', 'delivery_address' => 'KN 4 Ave, Kigali', 'total_revenue' => 25000, 'amount_paid' => 0, 'payment_status' => 'Credit', 'delivery_status' => 'Delivered', 'sales_channel' => 'Cash', 'sale_date' => '2025-01-20']));
        SaleItem::withoutEvents(fn () => SaleItem::create(['sale_id' => $sale->id, 'product_id' => $this->product->id, 'production_id' => $this->batch->id, 'quantity_sold' => 50, 'unit_price' => 500, 'line_total' => 25000]));
    }

    private function makeBatch(string $number, string $date, float $produced, float $remaining, array $extra = []): Production
    {
        // qc_status is not mass assignable (only the release action sets it), so apply it apart.
        $status = $extra['qc_status'] ?? null;
        unset($extra['qc_status']);

        return tap(Production::withoutEvents(fn () => Production::create(array_merge([
            'batch_id' => $number, 'product_id' => $this->product->id, 'quantity_produced' => $produced,
            'quantity_remaining' => $remaining, 'damaged' => 0, 'production_date' => $date, 'barcode' => 'TR-1',
            'inventory_record_id' => [['inventory_id' => $this->chilli->id, 'quantity_used' => 20]],
        ], $extra))), fn (Production $batch) => $status && $batch->forceFill(['qc_status' => $status])->saveQuietly());
    }

    public function test_a_batch_traces_back_to_its_supplier_lot_and_forward_to_its_customers(): void
    {
        $other = $this->makeBatch('SHK/BN/202', '2025-01-09', 100, 100);

        $this->actingAs($this->owner)
            ->get(route('admin.productions.show', ['production' => $this->batch, 'tab' => 'traceability']))
            ->assertOk()
            ->assertSeeInOrder(['Fresh Red Chilli', 'Green Leaf Cooperative', 'Lot GLC-2025-117'])
            ->assertSee($other->batch_id)
            ->assertSee('Simba Supermarket')
            ->assertSee('+250788111222');
    }

    public function test_a_supplier_lot_traces_forward_to_every_batch_and_customer(): void
    {
        $this->makeBatch('SHK/BN/202', '2025-01-09', 100, 100);

        $this->actingAs($this->owner)
            ->get(route('admin.inventory-records.recall', $this->chilli))
            ->assertOk()
            ->assertSee('SHK/BN/201')
            ->assertSee('SHK/BN/202')
            ->assertSee('Simba Supermarket');

        $file = $this->actingAs($this->owner)->get(route('admin.inventory-records.recall.export', $this->chilli));
        $file->assertOk();
        $this->assertStringStartsWith('PK', $file->streamedContent());
    }

    public function test_trace_reports_download_as_excel_and_pdf(): void
    {
        $this->actingAs($this->owner);

        $xlsx = $this->get(route('admin.productions.trace.export', [$this->batch, 'xlsx']));
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());

        $pdf = $this->get(route('admin.productions.trace.export', [$this->batch, 'pdf']));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_every_tab_renders(): void
    {
        $this->actingAs($this->owner);
        foreach (['overview', 'traceability', 'quality', 'label', 'incidents'] as $tab) {
            $this->get(route('admin.productions.show', ['production' => $this->batch, 'tab' => $tab]))->assertOk();
        }
        $this->get(route('admin.productions.label', [$this->batch, 'copies' => 3]))->assertOk()->assertSee('<svg', false)->assertSee('LOT SHK/BN/201');
    }

    public function test_held_rejected_and_expired_batches_cannot_be_sold(): void
    {
        $this->actingAs($this->owner);
        $sell = fn (Production $batch) => $this->post(route('admin.sales.store'), [
            'customer_name' => 'Walk-in', 'sale_date' => '2025-02-01', 'payment_status' => 'Credit',
            'delivery_status' => 'Pending', 'sales_channel' => 'Cash',
            'items' => [['product_id' => $this->product->id, 'production_id' => $batch->id, 'quantity_sold' => 1, 'unit_price' => 500]],
        ]);

        $this->batch->forceFill(['qc_status' => Production::QC_ON_HOLD])->save();
        $sell($this->batch)->assertSessionHasErrors('items');

        $expired = $this->makeBatch('SHK/BN/203', '2024-01-01', 50, 50, ['expiry_date' => '2024-06-01']);
        $sell($expired)->assertSessionHasErrors('items');

        $fine = $this->makeBatch('SHK/BN/204', '2025-01-10', 50, 50);
        $sell($fine)->assertSessionHasNoErrors();
    }

    public function test_the_shop_sells_the_earliest_best_before_first_and_skips_held_stock(): void
    {
        $this->batch->forceFill(['quantity_remaining' => 0])->save();
        $later = $this->makeBatch('LATE', '2025-03-01', 10, 10, ['best_before_date' => '2026-03-01']);
        $sooner = $this->makeBatch('SOON', '2025-02-01', 10, 10, ['best_before_date' => '2025-12-01']);
        $this->makeBatch('HELD', '2025-01-15', 10, 10, ['best_before_date' => '2025-06-01', 'qc_status' => Production::QC_ON_HOLD]);

        $items = app(StorefrontCheckoutService::class)->allocateItems(collect([[
            'product' => (object) ['id' => $this->product->id, 'name' => 'Neza Chilli Paste', 'sellable_qty' => 20],
            'quantity' => 15, 'unit_price' => 500, 'line_total' => 7500,
        ]]));

        $this->assertSame([$sooner->id, $later->id], array_column($items, 'production_id'));
        $this->assertSame([10.0, 5.0], array_column($items, 'quantity_sold'));
    }

    public function test_only_managers_release_hold_or_reject_a_batch(): void
    {
        $worker = User::factory()->create(['role' => User::ROLE_PRODUCTION, 'is_active' => true]);
        $this->batch->forceFill(['created_by' => $worker->id])->save();

        $this->actingAs($worker)->post(route('admin.productions.release', $this->batch), ['qc_status' => 'released'])->assertForbidden();

        $this->actingAs($this->owner)->post(route('admin.productions.release', $this->batch), ['qc_status' => 'on_hold'])
            ->assertSessionHasErrors('release_notes');
        $this->actingAs($this->owner)->post(route('admin.productions.release', $this->batch), ['qc_status' => 'on_hold', 'release_notes' => 'Seal failures on line 2'])
            ->assertRedirect();
        $this->assertSame(Production::QC_ON_HOLD, $this->batch->fresh()->qc_status);
        $this->assertSame($this->owner->id, $this->batch->fresh()->released_by);
    }

    public function test_quality_checks_and_incidents_are_recorded_against_the_batch(): void
    {
        $this->actingAs($this->owner);

        $this->post(route('admin.productions.quality-checks.store', $this->batch), [
            'check_type' => 'ph', 'measured_value' => 4.6, 'unit' => 'pH', 'spec_max' => 4.2, 'result' => 'pass', 'checked_at' => '2025-01-05 10:00',
        ])->assertSessionHas('status', fn ($s) => str_contains($s, 'outside the limits'));
        $this->assertTrue($this->batch->qualityChecks()->firstOrFail()->isOutOfSpec());

        $this->post(route('admin.productions.incidents.store', $this->batch), [
            'type' => 'complaint', 'title' => 'Loose cap', 'description' => 'Customer reported a loose cap.', 'opened_at' => '2025-02-01',
        ])->assertSessionHasNoErrors();
        $incident = $this->batch->incidents()->firstOrFail();

        $this->patch(route('admin.productions.incidents.close', [$this->batch, $incident]), ['actions_taken' => 'Replaced jar', 'disposition' => 'Destroyed'])
            ->assertSessionHasNoErrors();
        $this->assertSame('closed', $incident->fresh()->status);
    }

    public function test_new_batches_get_their_release_status_and_best_before_from_the_product(): void
    {
        $this->actingAs($this->owner);
        $payload = fn (string $number) => [
            'batch_id' => $number, 'product_id' => $this->product->id, 'quantity_produced' => 10, 'damaged' => 0,
            'production_date' => '2025-05-01', 'inventory_record_id' => [['inventory_id' => $this->chilli->id, 'quantity_used' => 1]],
        ];

        $this->post(route('admin.productions.store'), $payload('NEW-1'))->assertSessionHasNoErrors();
        $first = Production::where('batch_id', 'NEW-1')->firstOrFail();
        $this->assertSame(Production::QC_RELEASED, $first->qc_status);
        $this->assertSame('2025-10-28', $first->best_before_date->toDateString());

        $this->product->update(['requires_qc_release' => true]);
        $this->post(route('admin.productions.store'), $payload('NEW-2'))->assertSessionHasNoErrors();
        $this->assertSame(Production::QC_PENDING, Production::where('batch_id', 'NEW-2')->value('qc_status'));
    }

    public function test_a_rejected_delivery_cannot_go_into_a_batch(): void
    {
        $this->chilli->forceFill(['inspection_status' => 'rejected'])->saveQuietly();

        $this->actingAs($this->owner)->post(route('admin.productions.store'), [
            'batch_id' => 'REJ-1', 'product_id' => $this->product->id, 'quantity_produced' => 10, 'damaged' => 0,
            'production_date' => '2025-05-01', 'inventory_record_id' => [['inventory_id' => $this->chilli->id, 'quantity_used' => 1]],
        ])->assertSessionHasErrors('inventory_record_id');
    }

    public function test_production_staff_see_the_trace_but_not_other_peoples_batches(): void
    {
        $worker = User::factory()->create(['role' => User::ROLE_PRODUCTION, 'is_active' => true]);
        $mine = $this->makeBatch('MINE-1', '2025-01-12', 10, 10, ['created_by' => $worker->id]);

        $this->actingAs($worker)->get(route('admin.productions.show', ['production' => $mine, 'tab' => 'traceability']))
            ->assertOk()
            ->assertSee('Fresh Red Chilli')
            ->assertDontSee(route('admin.inventory-records.show', $this->chilli), false)
            ->assertDontSee('+250788111222');

        // Searching used to OR past the own-batches filter and list everyone's batches.
        $this->actingAs($worker)->get(route('admin.productions.index', ['search' => 'SHK']))
            ->assertOk()->assertDontSee('SHK/BN/201');
    }
}
