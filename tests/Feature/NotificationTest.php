<?php

namespace Tests\Feature;

use App\Jobs\SendLowStockAlerts;
use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_job_notifies_managers_once_per_day(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'manager']);
        $staff = User::factory()->create(['role' => 'staff']);
        Product::factory()->create(['name' => 'Nearly Empty', 'stock_quantity' => 2, 'low_stock_limit' => 5]);
        Product::factory()->create(['stock_quantity' => 20, 'low_stock_limit' => 5]);

        (new SendLowStockAlerts)->handle();
        (new SendLowStockAlerts)->handle();

        $this->assertCount(1, $admin->fresh()->notifications);
        $this->assertCount(1, $manager->fresh()->notifications);
        $this->assertCount(0, $staff->fresh()->notifications);
        $this->assertDatabaseCount('notification_dispatches', 2);
        $this->assertSame('Low stock warning', $admin->fresh()->notifications->first()->data['title']);
    }

    public function test_disabled_user_does_not_receive_low_stock_alerts(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'in_app_notifications' => false]);
        Product::factory()->create(['stock_quantity' => 1, 'low_stock_limit' => 5]);

        (new SendLowStockAlerts)->handle();

        $this->assertCount(0, $manager->fresh()->notifications);
    }

    public function test_user_can_view_and_mark_only_their_notification_as_read(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $other = User::factory()->create(['role' => 'manager']);
        $product = Product::factory()->create(['stock_quantity' => 1, 'low_stock_limit' => 5]);
        $user->refresh()->notify(new LowStockNotification($product));
        $other->refresh()->notify(new LowStockNotification($product));
        Sanctum::actingAs($user);

        $notification = $user->notifications()->first();
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data.data');
        $this->patchJson("/api/notifications/{$notification->id}/read")->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
        $this->patchJson('/api/notifications/'.$other->notifications()->first()->id.'/read')->assertNotFound();
    }

    public function test_user_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/notification-preferences', [
            'in_app_notifications' => false,
            'email_notifications' => true,
        ])->assertOk()->assertJsonPath('data.email_notifications', true);

        $this->assertFalse($user->fresh()->in_app_notifications);
        $this->assertTrue($user->fresh()->email_notifications);
    }
}
