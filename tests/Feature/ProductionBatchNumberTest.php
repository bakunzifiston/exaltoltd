<?php

namespace Tests\Feature;

use App\Models\InventoryRecord;
use App\Models\Product;
use App\Models\Production;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionBatchNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private InventoryRecord $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($this->owner);

        $this->material = InventoryRecord::query()->create([
            'supplier_name' => 'Test Supplier',
            'item_type' => 'Raw Material',
            'item_name' => 'Chilli',
            'quantity_in' => 1000,
            'quantity_out' => 0,
            'damaged' => 0,
            'storage_location' => 'Store A',
            'record_date' => '2026-08-01',
        ]);
    }

    public function test_the_same_batch_number_can_be_recorded_for_different_packagings(): void
    {
        $small = $this->product('Neza Sauce 250ml', 'TEST-BATCH-250');
        $large = $this->product('Neza Sauce 500ml', 'TEST-BATCH-500');

        $this->post(route('admin.productions.store'), $this->payload($small, 'SHK/BN/108'))
            ->assertSessionHasNoErrors();

        $this->post(route('admin.productions.store'), $this->payload($large, 'SHK/BN/108'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Production::query()->where('batch_id', 'SHK/BN/108')->count());
    }

    public function test_the_same_batch_number_cannot_be_recorded_twice_for_one_product(): void
    {
        $product = $this->product('Neza Sauce 250ml', 'TEST-BATCH-DUP');

        $this->post(route('admin.productions.store'), $this->payload($product, 'SHK/BN/108'))
            ->assertSessionHasNoErrors();

        $this->post(route('admin.productions.store'), $this->payload($product, ' SHK/BN/108 '))
            ->assertSessionHasErrors('batch_id');

        $this->assertSame(1, Production::query()->where('batch_id', 'SHK/BN/108')->count());
    }

    public function test_updating_a_production_keeps_its_own_batch_number(): void
    {
        $product = $this->product('Neza Sauce 250ml', 'TEST-BATCH-EDIT');

        $this->post(route('admin.productions.store'), $this->payload($product, 'SHK/BN/108'))
            ->assertSessionHasNoErrors();

        $production = Production::query()->firstOrFail();

        $this->put(
            route('admin.productions.update', $production),
            ['quantity_produced' => 25] + $this->payload($product, 'SHK/BN/108'),
        )->assertSessionHasNoErrors();

        $this->assertSame('SHK/BN/108', $production->fresh()->batch_id);
        $this->assertEquals(25, $production->fresh()->quantity_produced);
    }

    public function test_clearing_the_batch_number_on_update_keeps_the_existing_one(): void
    {
        $product = $this->product('Neza Sauce 250ml', 'TEST-BATCH-BLANK');

        $this->post(route('admin.productions.store'), $this->payload($product, 'SHK/BN/108'))
            ->assertSessionHasNoErrors();

        $production = Production::query()->firstOrFail();

        $this->put(route('admin.productions.update', $production), $this->payload($product, ''))
            ->assertSessionHasNoErrors();

        $this->assertSame('SHK/BN/108', $production->fresh()->batch_id);
    }

    private function product(string $type, string $barcode): Product
    {
        return Product::query()->create([
            'name' => $type,
            'type' => $type,
            'barcode' => $barcode,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Product $product, string $batchId): array
    {
        return [
            'batch_id' => $batchId,
            'product_id' => $product->id,
            'barcode' => $product->barcode,
            'quantity_produced' => 10,
            'damaged' => 0,
            'production_date' => '2026-08-13',
            'inventory_record_id' => [
                ['inventory_id' => $this->material->id, 'quantity_used' => 5],
            ],
        ];
    }
}
