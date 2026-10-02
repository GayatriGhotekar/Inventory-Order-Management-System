<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_an_order_with_calculated_totals(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $customer = Customer::factory()->create();
        $firstProduct = Product::factory()->create(['price' => 10.50, 'stock_quantity' => 20]);
        $secondProduct = Product::factory()->create(['price' => 5.00, 'stock_quantity' => 20]);
        Sanctum::actingAs($staff);

        $response = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $firstProduct->id, 'quantity' => 2],
                ['product_id' => $secondProduct->id, 'quantity' => 3],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'Pending')
            ->assertJsonPath('data.subtotal', '36.00')
            ->assertJsonPath('data.total', '36.00')
            ->assertJsonCount(2, 'data.items');

        $this->assertDatabaseHas('orders', [
            'id' => $response->json('data.id'),
            'created_by' => $staff->id,
            'status' => 'Pending',
        ]);

        // Phase 6 creates the order but does not deduct stock yet.
        $this->assertSame(20, $firstProduct->fresh()->stock_quantity);
    }

    public function test_order_uses_database_price_instead_of_request_price(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['price' => 25.00]);

        $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 1,
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', '25.00')
            ->assertJsonPath('data.total', '50.00');
    }

    public function test_duplicate_products_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('items.1.product_id');
    }

    public function test_orders_can_be_filtered_by_status(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        Order::factory()->create(['status' => 'Pending']);
        Order::factory()->create(['status' => 'Delivered']);

        $this->getJson('/api/orders?status=Pending')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.status', 'Pending');
    }

    public function test_manager_can_delete_a_pending_order_and_its_items(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        $orderId = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/orders/{$orderId}")->assertOk();
        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
        $this->assertDatabaseMissing('order_items', ['order_id' => $orderId]);
    }

    public function test_staff_cannot_delete_an_order(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        $order = Order::factory()->create();

        $this->deleteJson("/api/orders/{$order->id}")->assertForbidden();
    }

    public function test_order_routes_require_authentication(): void
    {
        $this->getJson('/api/orders')->assertUnauthorized();
        $this->postJson('/api/orders', [])->assertUnauthorized();
    }
}
