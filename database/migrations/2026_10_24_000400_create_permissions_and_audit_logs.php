<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->unique(['role', 'permission_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('entity_type')->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('response_status');
            $table->timestamp('created_at')->useCurrent()->index();
        });

        $permissions = [
            'catalog.manage' => 'Manage categories and products',
            'contacts.manage' => 'Manage suppliers and customers',
            'inventory.adjust' => 'Adjust warehouse inventory',
            'warehouses.manage' => 'Manage warehouses and transfers',
            'purchases.manage' => 'Manage purchase orders and goods receipts',
            'orders.create' => 'Create customer orders',
            'orders.manage' => 'Confirm, pack, ship, cancel, and delete orders',
            'returns.create' => 'Request returns for delivered orders',
            'returns.manage' => 'Approve, receive, and refund returns',
            'audit.view' => 'View audit history',
            'permissions.view' => 'View the role permission matrix',
        ];

        $now = now();
        foreach ($permissions as $name => $description) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => $name, 'description' => $description, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('role_permissions')->insert(['role' => 'admin', 'permission_id' => $permissionId]);

            if ($name !== 'permissions.view') {
                DB::table('role_permissions')->insert(['role' => 'manager', 'permission_id' => $permissionId]);
            }
            if (in_array($name, ['orders.create', 'returns.create'], true)) {
                DB::table('role_permissions')->insert(['role' => 'staff', 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
    }
};
