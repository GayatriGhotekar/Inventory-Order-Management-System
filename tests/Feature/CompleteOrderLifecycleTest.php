<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompleteOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_can_complete_full_lifecycle_and_updates_reports(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => '25.00',
            'stock_quantity' => 10,
        ]);
        Sanctum::actingAs($manager);

        $orderId = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'Pending')
            ->json('data.id');

        foreach (['Confirmed', 'Processing', 'Shipped', 'Delivered'] as $status) {
            $this->patchJson("/api/orders/{$orderId}/status", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.status', $status);
        }

        $this->assertSame(8, $product->fresh()->stock_quantity);
        $this->assertSame(1, StockMovement::where('product_id', $product->id)->where('type', 'ORDER')->count());

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_sales', '50.00');

        $this->getJson('/api/reports/best-selling-products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.total_quantity', 2);
    }
}
