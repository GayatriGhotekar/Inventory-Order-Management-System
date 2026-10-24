<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->enum('status', ['Active', 'Inactive'])->default('Active')->index();
            $table->timestamps();
        });

        Schema::create('warehouse_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('on_hand_quantity')->default(0);
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('damaged_quantity')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->timestamps();
            $table->unique(['warehouse_id', 'product_id']);
            $table->index(['warehouse_id', 'on_hand_quantity']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('product_id')->constrained()->restrictOnDelete();
        });

        $now = now();
        $warehouseId = DB::table('warehouses')->insertGetId([
            'name' => 'Main Warehouse',
            'code' => 'MAIN',
            'address' => 'Default warehouse for existing inventory',
            'is_default' => true,
            'status' => 'Active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('products')->orderBy('id')->each(function ($product) use ($warehouseId, $now) {
            DB::table('warehouse_inventories')->insert([
                'warehouse_id' => $warehouseId,
                'product_id' => $product->id,
                'on_hand_quantity' => $product->stock_quantity,
                'reserved_quantity' => 0,
                'damaged_quantity' => 0,
                'reorder_level' => $product->low_stock_limit,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        DB::table('stock_movements')->whereNull('warehouse_id')->update(['warehouse_id' => $warehouseId]);

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->enum('status', ['Draft', 'Approved', 'In Transit', 'Received', 'Cancelled'])->default('Draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('received_quantity')->default(0);
            $table->timestamps();
            $table->unique(['stock_transfer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
        Schema::dropIfExists('warehouse_inventories');
        Schema::dropIfExists('warehouses');
    }
};
