<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    private const STATUSES = [
        'Pending',
        'Confirmed',
        'Processing',
        'Shipped',
        'Delivered',
        'Cancelled',
    ];

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $orders = Order::with(['customer', 'creator:id,name'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Orders retrieved successfully',
            'data' => $orders,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('status', 'Active'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $order = DB::transaction(function () use ($request, $validator) {
            $validated = $validator->validated();
            $productIds = collect($validated['items'])->pluck('product_id');
            $products = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $subtotal = 0;

            foreach ($validated['items'] as $item) {
                $subtotal += round((float) $products[$item['product_id']]->price * $item['quantity'], 2);
            }

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'customer_id' => $validated['customer_id'],
                'status' => 'Pending',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $product = $products[$item['product_id']];
                $totalPrice = round((float) $product->price * $item['quantity'], 2);

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'total_price' => $totalPrice,
                ]);
            }

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $order->load(['customer', 'items.product']),
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully',
            'data' => $order->load(['customer', 'creator:id,name', 'items.product']),
        ]);
    }

    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::in([
                'Confirmed',
                'Processing',
                'Shipped',
                'Delivered',
                'Cancelled',
            ])],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        return DB::transaction(function () use ($request, $order, $validator) {
            $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);
            $newStatus = $validator->validated()['status'];

            if ($newStatus === 'Cancelled') {
                return $this->cancelOrder($request, $lockedOrder);
            }

            if ($lockedOrder->status === 'Pending' && $newStatus === 'Confirmed') {
                return $this->confirmOrder($request, $lockedOrder);
            }

            $nextStatuses = [
                'Confirmed' => 'Processing',
                'Processing' => 'Shipped',
                'Shipped' => 'Delivered',
            ];

            if (($nextStatuses[$lockedOrder->status] ?? null) !== $newStatus) {
                return response()->json([
                    'success' => false,
                    'message' => "Order cannot move from {$lockedOrder->status} to {$newStatus}",
                ], 409);
            }

            $lockedOrder->update(['status' => $newStatus]);

            return $this->statusSuccessResponse($lockedOrder);
        });
    }

    public function destroy(Order $order): JsonResponse
    {
        if ($order->status !== 'Pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can be deleted',
            ], 409);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
        ]);
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }

    private function confirmOrder(Request $request, Order $order): JsonResponse
    {
        $order->load('items');
        $productIds = $order->items->pluck('product_id')->sort()->values();
        $products = Product::whereIn('id', $productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($order->items as $item) {
            $product = $products[$item->product_id];

            if ($product->stock_quantity < $item->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock for {$product->name}",
                ], 422);
            }
        }

        foreach ($order->items as $item) {
            $product = $products[$item->product_id];
            $previousStock = $product->stock_quantity;
            $newStock = $previousStock - $item->quantity;

            $product->update(['stock_quantity' => $newStock]);
            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'ORDER',
                'quantity' => $item->quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reference' => $order->order_number,
                'created_by' => $request->user()->id,
            ]);
        }

        $order->update(['status' => 'Confirmed']);

        return $this->statusSuccessResponse($order);
    }

    private function cancelOrder(Request $request, Order $order): JsonResponse
    {
        if (! in_array($order->status, ['Pending', 'Confirmed', 'Processing'], true)) {
            return response()->json([
                'success' => false,
                'message' => "A {$order->status} order cannot be cancelled",
            ], 409);
        }

        if (in_array($order->status, ['Confirmed', 'Processing'], true)) {
            $order->load('items');
            $productIds = $order->items->pluck('product_id')->sort()->values();
            $products = Product::whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($order->items as $item) {
                $product = $products[$item->product_id];
                $previousStock = $product->stock_quantity;
                $newStock = $previousStock + $item->quantity;

                $product->update(['stock_quantity' => $newStock]);
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'RETURN',
                    'quantity' => $item->quantity,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'reference' => $order->order_number,
                    'created_by' => $request->user()->id,
                ]);
            }
        }

        $order->update(['status' => 'Cancelled']);

        return $this->statusSuccessResponse($order);
    }

    private function statusSuccessResponse(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $order->load(['customer', 'items.product']),
        ]);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors,
        ], 422);
    }
}
