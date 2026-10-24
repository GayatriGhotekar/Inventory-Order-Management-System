<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PermissionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_receive_clear_named_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->assertTrue($admin->hasPermission('permissions.view'));
        $this->assertTrue($manager->hasPermission('inventory.adjust'));
        $this->assertTrue($staff->hasPermission('orders.create'));
        $this->assertTrue($staff->hasPermission('returns.create'));
        $this->assertFalse($staff->hasPermission('orders.manage'));
        $this->assertFalse($staff->hasPermission('inventory.adjust'));
    }

    public function test_permission_middleware_replaces_broad_role_checks(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        $product = Product::factory()->create();

        $this->postJson('/api/categories', ['name' => 'Blocked'])->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: catalog.manage');
        $this->postJson("/api/inventory/{$product->id}/add", ['quantity' => 1])->assertForbidden()
            ->assertJsonPath('message', 'Missing permission: inventory.adjust');
    }

    public function test_successful_write_is_added_to_the_audit_log(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        Sanctum::actingAs($manager);

        $categoryId = $this->postJson('/api/categories', [
            'name' => 'Audited Category', 'description' => 'Created for audit test',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $manager->id,
            'action' => 'POST api/categories',
            'response_status' => 201,
        ]);

        $log = $this->getJson('/api/audit-logs')->assertOk()->json('data.data.0');
        $this->assertSame('Audited Category', $log['new_values']['name']);
        $this->assertSame($manager->id, $log['user']['id']);
        $this->assertNotNull(Category::find($categoryId));
    }

    public function test_failed_writes_and_passwords_are_not_audited(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        $this->postJson('/api/categories', ['name' => 'Blocked'])->assertForbidden();
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_staff_can_still_create_orders_but_cannot_manage_them(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        Sanctum::actingAs($staff);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();

        $orderId = $this->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/orders/{$orderId}/status", ['status' => 'Confirmed'])
            ->assertForbidden()->assertJsonPath('message', 'Missing permission: orders.manage');
    }

    public function test_only_admin_can_view_permission_matrix(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));
        $this->getJson('/api/permissions')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson('/api/permissions')->assertOk()
            ->assertJsonFragment(['name' => 'inventory.adjust']);
    }
}
