<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_a_default_warehouse(): void
    {
        $warehouse = Warehouse::where('is_default', true)->first();

        $this->assertNotNull($warehouse);
        $this->assertSame('MAIN', $warehouse->code);
    }

    public function test_manager_can_create_a_warehouse_and_adjust_its_stock(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $product = Product::factory()->create(['stock_quantity' => 10]);

        $warehouseId = $this->postJson('/api/warehouses', [
            'name' => 'Pune Warehouse', 'code' => 'PUNE', 'address' => 'Pune', 'status' => 'Active',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/warehouse-inventory/{$warehouseId}/{$product->id}/adjust", [
            'operation' => 'add', 'quantity' => 5, 'reference' => 'Opening stock',
        ])->assertOk()
            ->assertJsonPath('data.on_hand_quantity', 5)
            ->assertJsonPath('data.available_quantity', 5);

        $this->assertSame(15, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', ['warehouse_id' => $warehouseId, 'product_id' => $product->id, 'quantity' => 5]);
    }

    public function test_staff_can_view_but_cannot_manage_warehouses(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        $this->getJson('/api/warehouses')->assertOk();
        $this->getJson('/api/warehouse-inventory')->assertOk();
        $this->postJson('/api/warehouses', ['name' => 'Blocked', 'code' => 'NO', 'status' => 'Active'])->assertForbidden();
    }

    public function test_default_warehouse_cannot_be_deleted(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $warehouse = Warehouse::where('is_default', true)->firstOrFail();
        $this->deleteJson("/api/warehouses/{$warehouse->id}")->assertConflict();
    }

    public function test_default_warehouse_designation_cannot_be_reassigned_during_compatibility_phase(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $warehouse = Warehouse::create(['name' => 'Candidate', 'code' => 'CANDIDATE', 'status' => 'Active']);

        $this->putJson("/api/warehouses/{$warehouse->id}", [
            'name' => $warehouse->name, 'code' => $warehouse->code, 'status' => 'Active', 'is_default' => true,
        ])->assertConflict();

        $this->assertTrue(Warehouse::where('code', 'MAIN')->value('is_default'));
        $this->assertFalse($warehouse->fresh()->is_default);
    }

    public function test_damage_and_restore_change_available_stock_without_changing_total_stock(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $warehouse = Warehouse::where('is_default', true)->firstOrFail();
        WarehouseInventory::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'on_hand_quantity' => 10]);

        $this->postJson("/api/warehouse-inventory/{$warehouse->id}/{$product->id}/adjust", [
            'operation' => 'damage', 'quantity' => 3, 'reference' => 'Inspection damage',
        ])->assertOk()->assertJsonPath('data.available_quantity', 7);

        $this->postJson("/api/warehouse-inventory/{$warehouse->id}/{$product->id}/adjust", [
            'operation' => 'restore', 'quantity' => 2, 'reference' => 'Repaired',
        ])->assertOk()->assertJsonPath('data.available_quantity', 9);

        $this->assertSame(10, $product->fresh()->stock_quantity);
    }
}
