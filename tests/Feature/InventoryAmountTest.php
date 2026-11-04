<?php

namespace Tests\Feature;

use App\Models\InventoryRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAmountTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $changes = []): array
    {
        return array_replace([
            'supplier_name' => 'Test supplier', 'item_type' => 'Raw Material',
            'item_name' => 'Chilli', 'quantity_in' => 12.5, 'quantity_out' => 0,
            'unit_cost' => 123.45, 'damaged' => 0, 'storage_location' => 'Store',
            'record_date' => '2026-09-15', 'amount_paid' => 100,
            'payment_status' => 'Paid',
        ], $changes);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]));
    }

    public function test_create_calculates_total_without_client_total(): void
    {
        $this->get(route('admin.inventory-records.create'))->assertOk()->assertSee('Automatically calculated:');
        $this->post(route('admin.inventory-records.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $record = InventoryRecord::firstOrFail();
        $this->assertSame('1543.13', $record->total_amount);
        $this->assertSame('123.45', $record->unit_cost);
        $this->assertSame('Partial', $record->payment_status);
    }

    public function test_client_total_is_ignored_and_on_hand_is_used(): void
    {
        $this->post(route('admin.inventory-records.store'), $this->payload([
            'quantity_out' => 2.5, 'total_amount' => 999999, 'amount_paid' => 1234.5,
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $record = InventoryRecord::firstOrFail();
        $this->assertSame('1234.50', $record->total_amount);
        $this->assertSame('123.45', $record->unit_cost);
        $this->assertSame('Paid', $record->payment_status);
    }

    public function test_zero_cost_is_allowed_but_missing_and_negative_cost_are_rejected(): void
    {
        foreach ([null, -1] as $cost) {
            $this->post(route('admin.inventory-records.store'), $this->payload(['unit_cost' => $cost]))->assertSessionHasErrors('unit_cost');
        }
        $this->post(route('admin.inventory-records.store'), $this->payload(['unit_cost' => 0]))->assertSessionHasNoErrors();
        $this->assertSame('0.00', InventoryRecord::firstOrFail()->total_amount);
    }
}
