<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_the_required_totals(): void
    {
        [$user, $customer, $category] = $this->authenticate();
        Product::factory()->create(['category_id' => $category->id, 'stock_quantity' => 2, 'low_stock_limit' => 5]);
        Product::factory()->create(['category_id' => $category->id, 'stock_quantity' => 20, 'low_stock_limit' => 5]);
        $this->makeOrder($user, $customer, 'Delivered', '100.00');
        $this->makeOrder($user, $customer, 'Pending', '50.00');

        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('data.total_products', 2)
            ->assertJsonPath('data.total_customers', 1)
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.pending_orders', 1)
            ->assertJsonPath('data.todays_orders', 2)
            ->assertJsonPath('data.total_sales', '100.00')
            ->assertJsonPath('data.low_stock_product_count', 1);
    }

    public function test_sales_and_order_reports_are_correct(): void
    {
        [$user, $customer] = $this->authenticate();
        $this->makeOrder($user, $customer, 'Delivered', '100.00');
        $this->makeOrder($user, $customer, 'Pending', '900.00');

        $this->getJson('/api/reports/daily-sales?date='.today()->toDateString())
            ->assertOk()->assertJsonPath('data.total_orders', 1)
            ->assertJsonPath('data.total_sales', '100.00');

        $this->getJson('/api/reports/monthly-sales?year='.today()->year)
            ->assertOk()->assertJsonFragment([
                'month' => today()->format('Y-m'), 'total_orders' => 1, 'total_sales' => '100.00',
            ]);

        $this->getJson('/api/reports/orders-summary')->assertOk()
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonFragment(['status' => 'Delivered', 'total' => 1])
            ->assertJsonFragment(['status' => 'Pending', 'total' => 1]);
    }

    public function test_best_sellers_only_use_delivered_orders(): void
    {
        [$user, $customer, $category] = $this->authenticate();
        $sold = Product::factory()->create(['category_id' => $category->id]);
        $notSold = Product::factory()->create(['category_id' => $category->id]);
        $delivered = $this->makeOrder($user, $customer, 'Delivered', '60.00');
        $pending = $this->makeOrder($user, $customer, 'Pending', '500.00');
        OrderItem::create(['order_id' => $delivered->id, 'product_id' => $sold->id, 'quantity' => 3, 'unit_price' => 20, 'total_price' => 60]);
        OrderItem::create(['order_id' => $pending->id, 'product_id' => $notSold->id, 'quantity' => 10, 'unit_price' => 50, 'total_price' => 500]);

        $this->getJson('/api/reports/best-selling-products')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $sold->id)
            ->assertJsonPath('data.0.total_quantity', 3)
            ->assertJsonPath('data.0.total_sales', '60.00');
    }

    public function test_inventory_reports_and_validation_work(): void
    {
        [, , $category] = $this->authenticate();
        $low = Product::factory()->create(['category_id' => $category->id, 'stock_quantity' => 5, 'low_stock_limit' => 5]);
        Product::factory()->create(['category_id' => $category->id, 'stock_quantity' => 15, 'low_stock_limit' => 5]);

        $this->getJson('/api/reports/inventory')->assertOk()->assertJsonPath('data.total', 2);
        $this->getJson('/api/reports/low-stock')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $low->id);
        $this->getJson('/api/reports/daily-sales?date=invalid')->assertUnprocessable()
            ->assertJsonValidationErrors('date');
        $this->getJson('/api/reports/best-selling-products?limit=101')->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
    }

    public function test_dashboard_and_reports_require_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/reports/daily-sales')->assertUnauthorized();
    }

    private function authenticate(): array
    {
        $user = User::factory()->create(['role' => 'staff']);
        $customer = Customer::factory()->create();
        $category = Category::factory()->create();
        Sanctum::actingAs($user);

        return [$user, $customer, $category];
    }

    private function makeOrder(User $user, Customer $customer, string $status, string $total): Order
    {
        return Order::factory()->create([
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'status' => $status,
            'subtotal' => $total,
            'total' => $total,
        ]);
    }
}
