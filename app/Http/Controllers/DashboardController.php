<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Dashboard summary retrieved successfully',
            'data' => [
                'total_products' => Product::count(),
                'total_customers' => Customer::count(),
                'total_orders' => Order::count(),
                'pending_orders' => Order::where('status', 'Pending')->count(),
                'todays_orders' => Order::whereDate('created_at', today())->count(),
                'total_sales' => $this->money(Order::where('status', 'Delivered')->sum('total')),
                'low_stock_product_count' => Product::whereColumn(
                    'stock_quantity', '<=', 'low_stock_limit'
                )->count(),
            ],
        ]);
    }

    private function money(int|float|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
