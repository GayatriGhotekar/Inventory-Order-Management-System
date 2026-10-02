<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_category_and_product(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));

        $categoryId = $this->postJson('/api/categories', [
            'name' => 'Electronics',
            'description' => 'Electronic products',
        ])->assertCreated()->assertJsonPath('success', true)->json('data.id');

        $this->postJson('/api/products', [
            'category_id' => $categoryId,
            'name' => 'USB Keyboard',
            'sku' => 'KEYBOARD-001',
            'description' => 'Wired keyboard',
            'price' => 24.99,
            'stock_quantity' => 20,
            'low_stock_limit' => 5,
            'status' => 'Active',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sku', 'KEYBOARD-001');
    }

    public function test_product_sku_must_be_unique(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = Product::factory()->create(['sku' => 'DUPLICATE-001']);

        $this->postJson('/api/products', [
            'category_id' => $product->category_id,
            'name' => 'Another Product',
            'sku' => 'DUPLICATE-001',
            'price' => 10,
            'stock_quantity' => 1,
            'low_stock_limit' => 1,
            'status' => 'Active',
        ])->assertUnprocessable()->assertJsonValidationErrors('sku');
    }

    public function test_products_can_be_searched_and_filtered_by_category(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();
        Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Mechanical Keyboard',
            'sku' => 'KEY-001',
        ]);
        Product::factory()->create([
            'category_id' => $otherCategory->id,
            'name' => 'Wireless Mouse',
            'sku' => 'MOUSE-001',
        ]);

        $this->getJson("/api/products?search=Keyboard&category_id={$category->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.sku', 'KEY-001');
    }

    public function test_staff_can_read_but_cannot_create_products(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));

        $this->getJson('/api/products')->assertOk();
        $this->postJson('/api/products', [])->assertForbidden();
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $product = Product::factory()->create();

        $this->deleteJson("/api/categories/{$product->category_id}")
            ->assertConflict()
            ->assertJsonPath('success', false);
    }

    public function test_category_and_product_routes_require_authentication(): void
    {
        $this->getJson('/api/categories')->assertUnauthorized();
        $this->getJson('/api/products')->assertUnauthorized();
    }
}
