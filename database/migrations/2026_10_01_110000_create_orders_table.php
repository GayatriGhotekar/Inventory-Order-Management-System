<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->enum('status', [
                'Pending',
                'Confirmed',
                'Processing',
                'Shipped',
                'Delivered',
                'Cancelled',
            ])->default('Pending')->index();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total', 12, 2);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
