<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'customer_id' => Customer::factory(),
            'status' => 'Pending',
            'subtotal' => 0,
            'total' => 0,
            'created_by' => User::factory(),
        ];
    }
}
