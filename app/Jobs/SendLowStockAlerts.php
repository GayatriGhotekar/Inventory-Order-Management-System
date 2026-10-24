<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendLowStockAlerts implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function handle(): void
    {
        $products = Product::where('status', 'Active')->whereColumn('stock_quantity', '<=', 'low_stock_limit')->get();
        $users = User::whereIn('role', ['admin', 'manager'])
            ->where(fn ($query) => $query->where('in_app_notifications', true)->orWhere('email_notifications', true))
            ->get();

        foreach ($users as $user) {
            foreach ($products as $product) {
                $created = DB::table('notification_dispatches')->insertOrIgnore([
                    'user_id' => $user->id, 'type' => 'low_stock', 'reference' => (string) $product->id,
                    'dispatch_date' => today()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($created) {
                    $user->notify(new LowStockNotification($product));
                }
            }
        }
    }
}
