<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['IN', 'OUT', 'ORDER', 'ADJUSTMENT', 'RETURN'])->index();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('previous_stock');
            $table->unsignedInteger('new_stock');
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
