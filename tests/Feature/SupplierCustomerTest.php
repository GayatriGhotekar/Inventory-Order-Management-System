<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierCustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_manage_a_supplier(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'manager']));

        $supplierId = $this->postJson('/api/suppliers', [
            'name' => 'Example Supplier',
            'email' => 'supplier@example.com',
            'phone' => '1234567890',
            'address' => 'Example Address',
            'status' => 'Active',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/suppliers/{$supplierId}", [
            'name' => 'Updated Supplier',
            'email' => 'supplier@example.com',
            'phone' => '1234567890',
            'address' => 'Updated Address',
            'status' => 'Inactive',
        ])->assertOk()->assertJsonPath('data.status', 'Inactive');

        $this->deleteJson("/api/suppliers/{$supplierId}")->assertOk();
        $this->assertDatabaseMissing('suppliers', ['id' => $supplierId]);
    }

    public function test_admin_can_manage_a_customer(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $customerId = $this->postJson('/api/customers', [
            'name' => 'Example Customer',
            'email' => 'customer@example.com',
            'phone' => '1234567890',
            'address' => 'Example Address',
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/customers/{$customerId}")
            ->assertOk()
            ->assertJsonPath('data.email', 'customer@example.com');

        $this->deleteJson("/api/customers/{$customerId}")->assertOk();
        $this->assertDatabaseMissing('customers', ['id' => $customerId]);
    }

    public function test_supplier_and_customer_emails_must_be_unique(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Supplier::factory()->create(['email' => 'duplicate@example.com']);
        Customer::factory()->create(['email' => 'customer@example.com']);

        $this->postJson('/api/suppliers', [
            'name' => 'Duplicate Supplier',
            'email' => 'duplicate@example.com',
            'status' => 'Active',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/customers', [
            'name' => 'Duplicate Customer',
            'email' => 'customer@example.com',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_staff_has_read_only_access(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'staff']));
        Supplier::factory()->create();
        Customer::factory()->create();

        $this->getJson('/api/suppliers')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/customers')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/suppliers', [])->assertForbidden();
        $this->postJson('/api/customers', [])->assertForbidden();
    }

    public function test_supplier_and_customer_routes_require_authentication(): void
    {
        $this->getJson('/api/suppliers')->assertUnauthorized();
        $this->getJson('/api/customers')->assertUnauthorized();
    }
}
