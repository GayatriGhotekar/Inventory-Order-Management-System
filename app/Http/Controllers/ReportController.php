<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function dailySales(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $date = $request->input('date', today()->toDateString());
        $orders = Order::where('status', 'Delivered')->whereDate('created_at', $date);

        return response()->json([
            'success' => true,
            'message' => 'Daily sales report retrieved successfully',
            'data' => [
                'date' => $date,
                'total_orders' => (clone $orders)->count(),
                'total_sales' => $this->money($orders->sum('total')),
            ],
        ]);
    }

    public function monthlySales(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year' => ['nullable', 'integer', 'min:2000', 'max:'.today()->year],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $year = (int) $request->input('year', today()->year);
        $months = Order::where('status', 'Delivered')
            ->whereYear('created_at', $year)
            ->get(['total', 'created_at'])
            ->groupBy(fn (Order $order) => $order->created_at->format('Y-m'))
            ->map(fn ($orders, string $month) => [
                'month' => $month,
                'total_orders' => $orders->count(),
                'total_sales' => $this->money($orders->sum('total')),
            ])
            ->sortKeys()
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Monthly sales report retrieved successfully',
            'data' => ['year' => $year, 'months' => $months],
        ]);
    }

    public function ordersSummary(): JsonResponse
    {
        $ordersByStatus = Order::select('status', DB::raw('COUNT(*) as status_count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn (Order $order) => [
                'status' => $order->status,
                'total' => (int) $order->status_count,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Orders summary retrieved successfully',
            'data' => [
                'total_orders' => Order::count(),
                'orders_by_status' => $ordersByStatus,
            ],
        ]);
    }

    public function bestSellingProducts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $products = OrderItem::select(
            'products.id', 'products.name', 'products.sku',
            DB::raw('SUM(order_items.quantity) as total_quantity'),
            DB::raw('SUM(order_items.total_price) as total_sales')
        )
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.status', 'Delivered')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_quantity')
            ->limit((int) $request->input('limit', 10))
            ->get()
            ->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'total_quantity' => (int) $item->total_quantity,
                'total_sales' => $this->money($item->total_sales),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Best-selling products report retrieved successfully',
            'data' => $products,
        ]);
    }

    public function inventory(): JsonResponse
    {
        $products = Product::with('category')->orderBy('name')->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Current inventory report retrieved successfully',
            'data' => $products,
        ]);
    }

    public function lowStock(): JsonResponse
    {
        $products = Product::with('category')
            ->whereColumn('stock_quantity', '<=', 'low_stock_limit')
            ->orderBy('stock_quantity')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Low-stock products report retrieved successfully',
            'data' => $products,
        ]);
    }

    public function operations(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);
        $from = $data['from'] ?? today()->subDays(29)->toDateString();
        $to = $data['to'] ?? today()->toDateString();
        $warehouseId = $data['warehouse_id'] ?? null;

        $orders = Order::whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))->get();
        $delivered = $orders->where('status', 'Delivered');
        $orderIds = $orders->pluck('id');
        $returns = OrderReturn::whereIn('order_id', $orderIds)->where('status', 'Refunded')->get();
        $fulfilmentHours = $delivered->map(fn (Order $order) => $order->created_at->diffInMinutes($order->updated_at) / 60);

        $inventory = WarehouseInventory::with('product:id,name,price')
            ->when($warehouseId, fn ($query) => $query->where('warehouse_id', $warehouseId))->get();
        $inventoryValue = $inventory->sum(fn (WarehouseInventory $item) => $item->on_hand_quantity * (float) $item->product->price);

        $warehouses = Warehouse::with('inventories.product:id,price')
            ->when($warehouseId, fn ($query) => $query->whereKey($warehouseId))->get()
            ->map(function (Warehouse $warehouse) use ($orders) {
                $warehouseOrders = $orders->where('warehouse_id', $warehouse->id);

                return [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    'orders' => $warehouseOrders->count(),
                    'delivered_orders' => $warehouseOrders->where('status', 'Delivered')->count(),
                    'revenue' => $this->money($warehouseOrders->where('status', 'Delivered')->sum('total')),
                    'inventory_value' => $this->money($warehouse->inventories->sum(fn (WarehouseInventory $item) => $item->on_hand_quantity * (float) $item->product->price)),
                ];
            })->values();

        return response()->json(['success' => true, 'message' => 'Operations report retrieved successfully', 'data' => [
            'filters' => ['from' => $from, 'to' => $to, 'warehouse_id' => $warehouseId],
            'total_orders' => $orders->count(),
            'delivered_orders' => $delivered->count(),
            'revenue' => $this->money($delivered->sum('total')),
            'refunds' => $this->money($returns->sum('refund_amount')),
            'return_rate' => $delivered->count() ? round($returns->count() / $delivered->count() * 100, 2) : 0,
            'average_fulfilment_hours' => round($fulfilmentHours->average() ?? 0, 2),
            'inventory_value' => $this->money($inventoryValue),
            'low_stock_items' => $inventory->filter(fn (WarehouseInventory $item) => $item->available_quantity <= $item->reorder_level)->count(),
            'dead_stock_items' => $inventory->filter(fn (WarehouseInventory $item) => $item->available_quantity === 0)->count(),
            'warehouses' => $warehouses,
        ]]);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
        ], 422);
    }

    private function money(int|float|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
