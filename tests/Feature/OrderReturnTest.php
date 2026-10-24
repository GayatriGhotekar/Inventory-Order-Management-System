<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_return_can_be_requested_received_and_refunded(): void
    {
        [$order, $orderItem, $product, $warehouse, $manager] = $this->deliveredOrder(3, 7);
        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/returns', [
            'order_id' => $order->id,
            'reason' => 'Product not required',
            'items' => [['order_item_id' => $orderItem->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'Requested')
            ->assertJsonPath('data.refund_amount', '40.00');

        $returnId = $response->json('data.id');
        $returnItemId = $response->json('data.items.0.id');

        $this->patchJson("/api/returns/{$returnId}/status", ['status' => 'Approved'])
            ->assertOk()->assertJsonPath('data.status', 'Approved');
        $this->postJson("/api/returns/{$returnId}/receive", [
            'items' => [['order_return_item_id' => $returnItemId, 'condition' => 'Restock']],
        ])->assertOk()->assertJsonPath('data.status', 'Received');

        $this->assertSame(9, $product->fresh()->stock_quantity);
        $this->assertSame(9, WarehouseInventory::whereBelongsTo($warehouse)->whereBelongsTo($product)->value('on_hand_quantity'));
        $this->assertDatabaseHas('stock_movements', ['reference' => $response->json('data.return_number'), 'type' => 'RETURN', 'quantity' => 2]);

        $this->postJson("/api/returns/{$returnId}/refund")
            ->assertOk()->assertJsonPath('data.status', 'Refunded');
        $this->assertDatabaseHas('order_returns', ['id' => $returnId, 'refund_amount' => 40]);
    }

    public function test_damaged_return_is_not_added_to_available_stock(): void
    {
        [$order, $orderItem, $product, $warehouse, $manager] = $this->deliveredOrder(2, 8);
        Sanctum::actingAs($manager);
        $response = $this->postJson('/api/returns', [
            'order_id' => $order->id, 'reason' => 'Damaged in use',
            'items' => [['order_item_id' => $orderItem->id, 'quantity' => 1]],
        ])->assertCreated();
        $returnId = $response->json('data.id');
        $this->patchJson("/api/returns/{$returnId}/status", ['status' => 'Approved'])->assertOk();
        $this->postJson("/api/returns/{$returnId}/receive", [
            'items' => [['order_return_item_id' => $response->json('data.items.0.id'), 'condition' => 'Damaged']],
        ])->assertOk();

        $balance = WarehouseInventory::whereBelongsTo($warehouse)->whereBelongsTo($product)->firstOrFail();
        $this->assertSame(9, $balance->on_hand_quantity);
        $this->assertSame(1, $balance->damaged_quantity);
        $this->assertSame(8, $balance->available_quantity);
    }

    public function test_total_return_quantity_cannot_exceed_shipped_quantity(): void
    {
        [$order, $orderItem, , , $manager] = $this->deliveredOrder(2, 8);
        Sanctum::actingAs($manager);
        $this->postJson('/api/returns', [
            'order_id' => $order->id, 'reason' => 'First return',
            'items' => [['order_item_id' => $orderItem->id, 'quantity' => 2]],
        ])->assertCreated();

        $this->postJson('/api/returns', [
            'order_id' => $order->id, 'reason' => 'Duplicate return',
            'items' => [['order_item_id' => $orderItem->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('order_returns', 1);
    }

    public function test_non_delivered_order_cannot_be_returned(): void
    {
        Sanctum::actingAs($user = User::factory()->create(['role' => 'staff']));
        $order = Order::factory()->create(['status' => 'Shipped', 'created_by' => $user->id]);

        $this->postJson('/api/returns', [
            'order_id' => $order->id, 'reason' => 'Too early',
            'items' => [['order_item_id' => 1, 'quantity' => 1]],
        ])->assertConflict();
    }

    private function deliveredOrder(int $quantity, int $remainingStock): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $warehouse = Warehouse::where('is_default', true)->firstOrFail();
        $product = Product::factory()->create(['price' => 20, 'stock_quantity' => $remainingStock]);
        WarehouseInventory::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'on_hand_quantity' => $remainingStock]);
        $order = Order::factory()->create([
            'customer_id' => Customer::factory(), 'warehouse_id' => $warehouse->id,
            'status' => 'Delivered', 'created_by' => $manager->id,
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id, 'quantity' => $quantity, 'packed_quantity' => $quantity,
            'shipped_quantity' => $quantity, 'unit_price' => 20, 'total_price' => 20 * $quantity,
        ]);

        return [$order, $item, $product, $warehouse, $manager];
    }
}
