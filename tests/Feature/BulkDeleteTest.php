<?php

namespace Tests\Feature;

use App\Models\ContactSubmission;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER, 'is_active' => true]);
    }

    private function product(int $n): Product
    {
        return Product::create(['name' => 'Bulk Product '.$n, 'type' => 'Bulk Product '.$n, 'barcode' => 'BULK-'.$n, 'price' => 1000]);
    }

    public function test_selected_records_are_deleted_and_others_are_left_alone(): void
    {
        $owner = $this->owner();
        $products = collect(range(1, 3))->map(fn ($i) => $this->product($i));

        $response = $this->actingAs($owner)->post(route('admin.bulk-delete', 'products'), [
            'ids' => [$products[0]->id, $products[1]->id],
        ]);

        $response->assertRedirect();
        $this->assertSame(2, session('bulk_result')['deleted']);
        $this->assertSame([], session('bulk_result')['skipped']);
        $this->assertDatabaseMissing('products', ['id' => $products[0]->id]);
        $this->assertDatabaseMissing('products', ['id' => $products[1]->id]);
        $this->assertDatabaseHas('products', ['id' => $products[2]->id]);
    }

    public function test_each_modules_own_rules_still_apply_and_the_rest_of_the_batch_goes_through(): void
    {
        $owner = $this->owner();
        $other = User::factory()->create(['role' => User::ROLE_SALES, 'is_active' => true]);

        $this->actingAs($owner)->post(route('admin.bulk-delete', 'users'), ['ids' => [$owner->id, $other->id]]);

        $result = session('bulk_result');
        $this->assertSame(1, $result['deleted']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString('own account', $result['skipped'][0]['reason']);
        $this->assertNotNull(User::find($owner->id));
        $this->assertNull(User::find($other->id));
    }

    public function test_a_role_without_permission_cannot_bulk_delete(): void
    {
        $sales = User::factory()->create(['role' => User::ROLE_SALES, 'is_active' => true]);
        $product = $this->product(1);

        $this->actingAs($sales)->post(route('admin.bulk-delete', 'products'), ['ids' => [$product->id]])->assertForbidden();
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_unknown_modules_and_empty_selections_are_rejected(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('admin.bulk-delete', 'nonsense'), ['ids' => [1]])->assertNotFound();
        $this->actingAs($owner)->post(route('admin.bulk-delete', 'products'), ['ids' => []])->assertSessionHasErrors('ids');
    }

    public function test_list_pages_render_the_selection_hook(): void
    {
        $owner = $this->owner();
        ContactSubmission::create(['name' => 'A', 'email' => 'a@example.com', 'message' => 'Hi']);

        $this->actingAs($owner)->get(route('admin.contact-submissions.index'))
            ->assertOk()->assertSee(route('admin.bulk-delete', 'contact-submissions'), false);
    }
}
