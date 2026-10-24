<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_moves_stock_between_warehouses_through_the_valid_lifecycle(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($manager);
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $source = Warehouse::where('is_default', true)->firstOrFail();
        $destination = Warehouse::create(['name' => 'South Warehouse', 'code' => 'SOUTH', 'status' => 'Active']);
        WarehouseInventory::create(['warehouse_id' => $source->id, 'product_id' => $product->id, 'on_hand_quantity' => 10]);

        $transferId = $this->postJson('/api/stock-transfers', [
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
        ])->assertCreated()->assertJsonPath('data.status', 'Draft')->json('data.id');

        $this->patchJson("/api/stock-transfers/{$transferId}/status", ['status' => 'Approved'])->assertOk()->assertJsonPath('data.status', 'Approved');
        $this->patchJson("/api/stock-transfers/{$transferId}/status", ['status' => 'In Transit'])->assertOk()->assertJsonPath('data.status', 'In Transit');
        $this->assertSame(6, WarehouseInventory::whereBelongsTo($source)->whereBelongsTo($product)->value('on_hand_quantity'));
        $this->assertSame(6, $product->fresh()->stock_quantity);

        $this->patchJson("/api/stock-transfers/{$transferId}/status", ['status' => 'Received'])->assertOk()->assertJsonPath('data.status', 'Received');
        $this->assertSame(4, WarehouseInventory::whereBelongsTo($destination)->whereBelongsTo($product)->value('on_hand_quantity'));
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(2, StockMovement::where('reference', 'like', 'TRF-%')->count());
    }

    public function test_transfer_rejects_insufficient_stock_and_invalid_transitions(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $product = Product::factory()->create(['stock_quantity' => 2]);
        $source = Warehouse::where('is_default', true)->firstOrFail();
        $destination = Warehouse::create(['name' => 'North Warehouse', 'code' => 'NORTH', 'status' => 'Active']);
        WarehouseInventory::create(['warehouse_id' => $source->id, 'product_id' => $product->id, 'on_hand_quantity' => 2]);

        $transferId = $this->postJson('/api/stock-transfers', [
            'source_warehouse_id' => $source->id, 'destination_warehouse_id' => $destination->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/stock-transfers/{$transferId}/status", ['status' => 'Received'])->assertConflict();
        $this->patchJson("/api/stock-transfers/{$transferId}/status", ['status' => 'Approved'])->assertConflict();
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_same_source_and_destination_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $warehouse = Warehouse::where('is_default', true)->firstOrFail();
        $product = Product::factory()->create();

        $this->postJson('/api/stock-transfers', [
            'source_warehouse_id' => $warehouse->id, 'destination_warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('source_warehouse_id');
    }
}
