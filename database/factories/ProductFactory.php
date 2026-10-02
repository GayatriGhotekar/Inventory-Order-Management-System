<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-####-????'),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 1, 1000),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'low_stock_limit' => 10,
            'status' => 'Active',
        ];
    }
}
