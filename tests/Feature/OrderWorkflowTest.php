<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WarehouseInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_an_order_reserves_stock_without_deducting_it(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $order = $this->createOrder($manager, $product, 3);
        Sanctum::actingAs($manager);

        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'Confirmed',
        ])->assertOk()->assertJsonPath('data.status', 'Confirmed');

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(3, WarehouseInventory::where('product_id', $product->id)->value('reserved_quantity'));
        $this->assertNotNull($order->fresh()->inventory_reserved_at);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_insufficient_stock_rolls_back_the_confirmation(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = Product::factory()->create(['stock_quantity' => 2]);
        $order = $this->createOrder($manager, $product, 3);
        Sanctum::actingAs($manager);

        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'Confirmed',
        ])->assertUnprocessable()->assertJsonPath('success', false);

        $this->assertSame('Pending', $order->fresh()->status);
        $this->assertSame(2, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_order_cannot_reserve_stock_twice(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $order = $this->createOrder($manager, $product, 3);
        Sanctum::actingAs($manager);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'Confirmed'])
            ->assertOk();
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'Confirmed'])
            ->assertConflict();

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(3, WarehouseInventory::where('product_id', $product->id)->value('reserved_quantity'));
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_cancelling_a_confirmed_order_releases_reservation_once(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $order = $this->createOrder($manager, $product, 3);
        Sanctum::actingAs($manager);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'Confirmed'])
            ->assertOk();
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'Cancelled'])
            ->assertOk()->assertJsonPath('data.status', 'Cancelled');
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'Cancelled'])
            ->assertConflict();

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(0, WarehouseInventory::where('product_id', $product->id)->value('reserved_quantity'));
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_pending_order_can_be_cancelled_without_changing_stock(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $order = $this->createOrder($manager, $product, 3);
        Sanctum::actingAs($manager);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'Cancelled'])
            ->assertOk();

        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_order_must_follow_the_status_sequence(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $order = $this->createOrder($manager, $product, 1);
        Sanctum::actingAs($manager);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'Processing'])
            ->assertConflict();
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'Confirmed'])
            ->assertOk();
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'Processing'])
            ->assertOk();
        $this->patchJson("/api/orders/{$order->id}/status", [
            'status' => 'Shipped',
            'carrier' => 'BlueDart',
            'tracking_number' => 'TRACK-001',
            'shipping_address' => 'Pune, Maharashtra',
        ])
            ->assertOk();
        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'Delivered'])
            ->assertOk();

        $this->assertSame('Delivered', $order->fresh()->status);
        $this->assertSame(9, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('order_shipments', [
            'order_id' => $order->id,
            'carrier' => 'BlueDart',
            'tracking_number' => 'TRACK-001',
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'packed_quantity' => 1,
            'shipped_quantity' => 1,
        ]);
        $this->assertDatabaseCount('order_status_histories', 4);
    }

    public function test_staff_cannot_change_order_status(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $order = Order::factory()->create(['created_by' => $staff->id]);
        Sanctum::actingAs($staff);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => 'Confirmed'])
            ->assertForbidden();
    }

    private function createOrder(User $user, Product $product, int $quantity): Order
    {
        $order = Order::factory()->create([
            'customer_id' => Customer::factory(),
            'created_by' => $user->id,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
            'total_price' => (float) $product->price * $quantity,
        ]);

        return $order;
    }
}
