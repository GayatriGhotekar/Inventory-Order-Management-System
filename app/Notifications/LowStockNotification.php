<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification
{
    use Queueable;

    public function __construct(private Product $product) {}

    public function via(object $notifiable): array
    {
        $channels = [];
        if ($notifiable->in_app_notifications !== false) {
            $channels[] = 'database';
        }
        if ($notifiable->email_notifications) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Low stock warning: '.$this->product->name)
            ->line($this->toArray($notifiable)['message'])
            ->line("SKU: {$this->product->sku}")
            ->line("Low-stock limit: {$this->product->low_stock_limit}");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Low stock warning',
            'message' => "{$this->product->name} has only {$this->product->stock_quantity} item(s) left.",
            'product_id' => $this->product->id,
            'sku' => $this->product->sku,
            'stock_quantity' => $this->product->stock_quantity,
            'low_stock_limit' => $this->product->low_stock_limit,
        ];
    }
}
