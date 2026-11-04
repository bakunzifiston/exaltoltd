<?php

namespace Tests\Feature;

use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionSample;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Records that are in use are not refused outright: the user sees what would be lost, then may delete anyway. */
class ForceDeleteTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Product $product;

    private InventoryRecord $lot;

    private Production $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);
        $this->product = Product::create(['name' => 'Passion Juice', 'type' => 'Passion Juice', 'barcode' => 'FD-1', 'price' => 500]);
        $this->lot = InventoryRecord::withoutEvents(fn () => InventoryRecord::create(['supplier_name' => 'Farm', 'item_type' => 'Raw Material', 'item_name' => 'Passion Fruit', 'quantity_in' => 100, 'quantity_out' => 40, 'damaged' => 0, 'storage_location' => 'Store', 'record_date' => '2025-01-02', 'unit_cost' => 1000, 'total_amount' => 100000]));
        $this->batch = Production::withoutEvents(fn () => Production::create(['batch_id' => 'FD-BATCH', 'product_id' => $this->product->id, 'quantity_produced' => 200, 'quantity_remaining' => 100, 'damaged' => 0, 'production_date' => '2025-01-05', 'inventory_record_id' => [['inventory_id' => $this->lot->id, 'quantity_used' => 40]]]));
    }

    public function test_a_used_delivery_asks_first_then_deletes_when_confirmed(): void
    {
        $url = route('admin.inventory-records.destroy', $this->lot);

        $this->actingAs($this->owner)->delete($url)->assertSessionHas('force_prompt');
        $this->assertNotNull(InventoryRecord::find($this->lot->id), 'Nothing is deleted before the user confirms.');
        $prompt = session('force_prompt');
        $this->assertSame([$this->lot->id], $prompt['ids']);
        $this->assertStringContainsString('FD-BATCH', $prompt['items'][0]['detail']);

        // Confirming goes through the bulk endpoint with force=1.
        $this->actingAs($this->owner)->post(route('admin.bulk-delete', 'inventory-records'), ['ids' => [$this->lot->id], 'force' => 1]);
        $this->assertNull(InventoryRecord::find($this->lot->id));
        $this->assertNotNull(InventoryRecord::withTrashed()->find($this->lot->id), 'It is archived, so batch cost history can still read it.');
    }

    public function test_bulk_delete_collects_used_records_into_one_confirmation(): void
    {
        $this->actingAs($this->owner)->post(route('admin.bulk-delete', 'inventory-records'), ['ids' => [$this->lot->id]]);

        $result = session('bulk_result');
        $this->assertSame(0, $result['deleted']);
        $this->assertSame([$this->lot->id], $result['confirm']['ids']);
        $this->assertNotNull(InventoryRecord::find($this->lot->id));
    }

    public function test_a_batch_with_sales_and_samples_warns_then_deletes_everything_it_owns(): void
    {
        $sale = Sale::withoutEvents(fn () => Sale::create(['sales_id' => 'FD-SALE', 'customer_name' => 'Shop', 'total_revenue' => 50000, 'amount_paid' => 0, 'payment_status' => 'Unpaid', 'delivery_status' => 'Delivered', 'sales_channel' => 'Cash', 'sale_date' => '2025-01-10', 'created_by' => $this->owner->id]));
        SaleItem::withoutEvents(fn () => SaleItem::create(['sale_id' => $sale->id, 'product_id' => $this->product->id, 'production_id' => $this->batch->id, 'quantity_sold' => 100, 'unit_price' => 500, 'line_total' => 50000]));
        $sample = ProductionSample::withoutEvents(fn () => ProductionSample::create(['product_id' => $this->product->id, 'production_id' => $this->batch->id, 'quantity' => 5, 'sample_date' => '2025-02-03', 'recipient_type' => 'event', 'recipient_name' => 'Fair', 'purpose' => 'Tasting', 'created_by' => $this->owner->id]));

        $this->actingAs($this->owner)->delete(route('admin.productions.destroy', $this->batch))->assertSessionHas('force_prompt');
        $detail = session('force_prompt')['items'][0]['detail'];
        $this->assertStringContainsString('1 sale line', $detail);
        $this->assertStringContainsString('1 production sample', $detail);
        $this->assertNotNull(Production::find($this->batch->id));

        $this->actingAs($this->owner)->post(route('admin.bulk-delete', 'productions'), ['ids' => [$this->batch->id], 'force' => 1]);

        $this->assertNull(Production::find($this->batch->id));
        $this->assertDatabaseMissing('sale_items', ['production_id' => $this->batch->id]);
        $this->assertDatabaseMissing('production_samples', ['id' => $sample->id]);
        $this->assertNotNull(Sale::find($sale->id), 'The sale itself stays.');
    }

    public function test_a_batch_nothing_depends_on_deletes_straight_away(): void
    {
        $this->actingAs($this->owner)->delete(route('admin.productions.destroy', $this->batch))->assertSessionMissing('force_prompt');
        $this->assertNull(Production::find($this->batch->id));
    }

    public function test_confirming_still_needs_the_delete_permission(): void
    {
        $sales = User::factory()->create(['role' => User::ROLE_SALES, 'is_active' => true]);

        $this->actingAs($sales)->post(route('admin.bulk-delete', 'inventory-records'), ['ids' => [$this->lot->id], 'force' => 1])->assertForbidden();
        $this->assertNotNull(InventoryRecord::find($this->lot->id));
    }

    public function test_the_warning_is_shown_on_the_page_with_a_delete_anyway_button(): void
    {
        $this->actingAs($this->owner)->from(route('admin.inventory-records.index'))
            ->followingRedirects()
            ->delete(route('admin.inventory-records.destroy', $this->lot))
            ->assertOk()
            ->assertSee('in use. Delete anyway?')
            ->assertSee('Nothing has been deleted yet')
            ->assertSee('FD-BATCH')
            ->assertSee('name="force" value="1"', false);
    }
}
