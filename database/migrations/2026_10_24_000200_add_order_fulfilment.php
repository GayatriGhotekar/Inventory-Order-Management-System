<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
            $table->timestamp('inventory_reserved_at')->nullable();
            $table->timestamp('inventory_deducted_at')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('packed_quantity')->default(0);
            $table->unsignedInteger('shipped_quantity')->default(0);
        });

        Schema::create('order_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable()->unique();
            $table->text('shipping_address')->nullable();
            $table->timestamp('shipped_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        $defaultWarehouseId = DB::table('warehouses')->where('is_default', true)->value('id');
        if ($defaultWarehouseId) {
            DB::table('orders')->whereNull('warehouse_id')->update(['warehouse_id' => $defaultWarehouseId]);
        }

        DB::table('orders')
            ->whereIn('status', ['Confirmed', 'Processing', 'Shipped', 'Delivered'])
            ->update(['inventory_deducted_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_shipments');
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['packed_quantity', 'shipped_quantity']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn(['inventory_reserved_at', 'inventory_deducted_at']);
        });
    }
};
