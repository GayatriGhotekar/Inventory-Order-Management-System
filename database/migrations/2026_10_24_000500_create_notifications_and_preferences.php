<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('notification_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('reference');
            $table->date('dispatch_date');
            $table->timestamps();
            $table->unique(['user_id', 'type', 'reference', 'dispatch_date'], 'notification_dispatch_unique');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('in_app_notifications')->default(true);
            $table->boolean('email_notifications')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['in_app_notifications', 'email_notifications']);
        });
        Schema::dropIfExists('notification_dispatches');
        Schema::dropIfExists('notifications');
    }
};
