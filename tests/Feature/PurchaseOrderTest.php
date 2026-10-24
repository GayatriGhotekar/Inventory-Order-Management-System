<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_approve_and_partially_receive_a_purchase_order(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $supplier = Supplier::factory()->create(['status' => 'Active']);
        $warehouse = Warehouse::where('is_default', true)->firstOrFail();
        $product = Product::factory()->create(['stock_quantity' => 5]);
        WarehouseInventory::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'on_hand_quantity' => 5]);

        $response = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'expected_date' => today()->addWeek()->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_cost' => 12.50]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'Draft')
            ->assertJsonPath('data.total', '125.00');

        $orderId = $response->json('data.id');
        $itemId = $response->json('data.items.0.id');

        $this->patchJson("/api/purchase-orders/{$orderId}/status", ['status' => 'Approved'])
            ->assertOk()->assertJsonPath('data.status', 'Approved');

        $this->postJson("/api/purchase-orders/{$orderId}/receive", [
            'supplier_invoice_reference' => 'INV-1001',
            'items' => [[
                'purchase_order_item_id' => $itemId,
                'accepted_quantity' => 4,
                'damaged_quantity' => 1,
                'rejected_quantity' => 0,
            ]],
        ])->assertCreated();

        $this->assertDatabaseHas('purchase_orders', ['id' => $orderId, 'status' => 'Partially Received']);
        $this->assertDatabaseHas('purchase_order_items', ['id' => $itemId, 'received_quantity' => 5]);
        $this->assertSame(9, $product->fresh()->stock_quantity);
        $this->assertSame(9, WarehouseInventory::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('on_hand_quantity'));
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'quantity' => 4]);
    }

    public function test_final_receipt_completes_the_purchase_order(): void
    {
        [$orderId, $itemId, $product, $warehouse] = $this->createApprovedOrder(6);

        $this->postJson("/api/purchase-orders/{$orderId}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'accepted_quantity' => 6, 'damaged_quantity' => 0, 'rejected_quantity' => 0]],
        ])->assertCreated();

        $this->assertDatabaseHas('purchase_orders', ['id' => $orderId, 'status' => 'Received']);
        $this->assertSame(6, $product->fresh()->stock_quantity);
        $this->assertSame(6, WarehouseInventory::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->value('on_hand_quantity'));
    }

    public function test_receipt_cannot_exceed_remaining_order_quantity(): void
    {
        [$orderId, $itemId, $product] = $this->createApprovedOrder(3);

        $this->postJson("/api/purchase-orders/{$orderId}/receive", [
            'items' => [['purchase_order_item_id' => $itemId, 'accepted_quantity' => 4, 'damaged_quantity' => 0, 'rejected_quantity' => 0]],
        ])->assertUnprocessable();

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_staff_has_read_only_purchase_order_access(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        $this->getJson('/api/purchase-orders')->assertOk();
        $this->postJson('/api/purchase-orders', [])->assertForbidden();
    }

    private function createApprovedOrder(int $quantity): array
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $supplier = Supplier::factory()->create(['status' => 'Active']);
        $warehouse = Warehouse::where('is_default', true)->firstOrFail();
        $product = Product::factory()->create(['stock_quantity' => 0]);
        WarehouseInventory::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id]);

        $response = $this->postJson('/api/purchase-orders', [
            'supplier_id' => $supplier->id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity, 'unit_cost' => 10]],
        ])->assertCreated();

        $orderId = $response->json('data.id');
        $this->patchJson("/api/purchase-orders/{$orderId}/status", ['status' => 'Approved'])->assertOk();

        return [$orderId, $response->json('data.items.0.id'), $product, $warehouse];
    }
}
