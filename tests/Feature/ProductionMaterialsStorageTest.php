<?php

namespace Tests\Feature;

use App\Models\Production;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductionMaterialsStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_materials_are_stored_as_a_json_array(): void
    {
        $this->assertContains(
            Schema::getColumnType('productions', 'inventory_record_id'),
            ['json', 'text'],
        );

        $productId = DB::table('products')->insertGetId([
            'name' => 'Test chilli',
            'type' => 'Test chilli 80g',
            'barcode' => 'TEST-PRODUCTION-MATERIALS',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $materials = [[
            'inventory_id' => 114,
            'quantity_used' => '1.2',
        ]];

        $production = Production::withoutEvents(fn () => Production::query()->create([
            'batch_id' => 'TEST-JSON-MATERIALS',
            'product_id' => $productId,
            'barcode' => 'TEST-PRODUCTION-MATERIALS',
            'quantity_produced' => 10,
            'quantity_remaining' => 10,
            'damaged' => 0,
            'inventory_record_id' => $materials,
            'production_date' => '2026-07-31',
        ]));

        $this->assertSame($materials, $production->fresh()->inventory_record_id);
    }
}
