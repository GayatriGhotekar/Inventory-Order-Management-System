<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_add_stock_and_movement_is_created(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = Product::factory()->create(['stock_quantity' => 10]);
        Sanctum::actingAs($manager);

        $this->postJson("/api/inventory/{$product->id}/add", [
            'quantity' => 5,
            'reference' => 'Supplier delivery',
        ])->assertOk()
            ->assertJsonPath('data.product.stock_quantity', 15)
            ->assertJsonPath('data.movement.type', 'IN');

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'quantity' => 5,
            'previous_stock' => 10,
            'new_stock' => 15,
            'created_by' => $manager->id,
        ]);
    }

    public function test_manager_can_remove_stock(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $this->postJson("/api/inventory/{$product->id}/remove", [
            'quantity' => 4,
            'reference' => 'Damaged items',
        ])->assertOk()
            ->assertJsonPath('data.product.stock_quantity', 6)
            ->assertJsonPath('data.movement.type', 'OUT');
    }

    public function test_stock_cannot_become_negative(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = Product::factory()->create(['stock_quantity' => 3]);

        $this->postJson("/api/inventory/{$product->id}/remove", [
            'quantity' => 4,
        ])->assertUnprocessable()->assertJsonPath('message', 'Insufficient stock');

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_low_stock_endpoint_returns_only_low_stock_products(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        Product::factory()->create(['stock_quantity' => 5, 'low_stock_limit' => 5]);
        Product::factory()->create(['stock_quantity' => 20, 'low_stock_limit' => 5]);

        $this->getJson('/api/inventory/low-stock')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_staff_can_view_inventory_but_cannot_change_stock(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        $product = Product::factory()->create();

        $this->getJson('/api/inventory')->assertOk();
        $this->getJson("/api/inventory/{$product->id}")->assertOk();
        $this->postJson("/api/inventory/{$product->id}/add", ['quantity' => 1])
            ->assertForbidden();
    }

    public function test_product_creation_and_update_record_stock_movements(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::factory()->create();

        $productId = $this->postJson('/api/products', [
            'category_id' => $category->id,
            'name' => 'Tracked Product',
            'sku' => 'TRACK-001',
            'price' => 10,
            'stock_quantity' => 8,
            'low_stock_limit' => 2,
            'status' => 'Active',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/products/{$productId}", [
            'category_id' => $category->id,
            'name' => 'Tracked Product',
            'sku' => 'TRACK-001',
            'price' => 10,
            'stock_quantity' => 6,
            'low_stock_limit' => 2,
            'status' => 'Active',
        ])->assertOk();

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productId,
            'type' => 'IN',
            'previous_stock' => 0,
            'new_stock' => 8,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productId,
            'type' => 'ADJUSTMENT',
            'previous_stock' => 8,
            'new_stock' => 6,
        ]);
    }

    public function test_inventory_routes_require_authentication(): void
    {
        $this->getJson('/api/inventory')->assertUnauthorized();
        $this->getJson('/api/inventory/low-stock')->assertUnauthorized();
    }
}
