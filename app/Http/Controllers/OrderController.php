<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderShipment;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
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

        $orders = Order::with(['customer', 'warehouse', 'creator:id,name'])
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
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
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
            $warehouse = isset($validated['warehouse_id'])
                ? Warehouse::whereKey($validated['warehouse_id'])->where('status', 'Active')->firstOrFail()
                : Warehouse::where('is_default', true)->where('status', 'Active')->firstOrFail();
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
                'warehouse_id' => $warehouse->id,
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

            OrderStatusHistory::create([
                'order_id' => $order->id, 'from_status' => null, 'to_status' => 'Pending',
                'notes' => 'Order created', 'changed_by' => $request->user()->id,
            ]);

            return $order;
        });

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $order->load(['customer', 'warehouse', 'items.product', 'statusHistory.user:id,name']),
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully',
            'data' => $order->load(['customer', 'warehouse', 'creator:id,name', 'items.product', 'shipments', 'statusHistory.user:id,name']),
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
            'carrier' => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:255', 'unique:order_shipments,tracking_number'],
            'shipping_address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
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

                if ($newStatus === 'Processing') {
                    $lockedOrder->items()->update(['packed_quantity' => DB::raw('quantity')]);
                    $this->changeStatus($lockedOrder, 'Processing', $request);
                } elseif ($newStatus === 'Shipped') {
                    $this->shipOrder($lockedOrder, $request);
                } else {
                    $this->changeStatus($lockedOrder, 'Delivered', $request);
                }

                return $this->statusSuccessResponse($lockedOrder);
            });
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
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
        $warehouse = $order->warehouse ?: Warehouse::where('is_default', true)->firstOrFail();
        foreach ($products as $product) {
            WarehouseInventory::firstOrCreate(['warehouse_id' => $warehouse->id, 'product_id' => $product->id], ['on_hand_quantity' => $product->stock_quantity, 'reorder_level' => $product->low_stock_limit]);
        }
        $balances = WarehouseInventory::where('warehouse_id', $warehouse->id)->whereIn('product_id', $productIds)
            ->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id');

        foreach ($order->items as $item) {
            $product = $products[$item->product_id];

            if (! isset($balances[$item->product_id]) || $balances[$item->product_id]->available_quantity < $item->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient stock for {$product->name}",
                ], 422);
            }
        }

        foreach ($order->items as $item) {
            $balances[$item->product_id]->increment('reserved_quantity', $item->quantity);
        }

        $order->update(['inventory_reserved_at' => now()]);
        $this->changeStatus($order, 'Confirmed', $request, 'Inventory reserved');

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
            $warehouse = $order->warehouse ?: Warehouse::where('is_default', true)->firstOrFail();
            foreach ($products as $product) {
                WarehouseInventory::firstOrCreate(['warehouse_id' => $warehouse->id, 'product_id' => $product->id], ['on_hand_quantity' => $product->stock_quantity, 'reorder_level' => $product->low_stock_limit]);
            }
            $balances = WarehouseInventory::where('warehouse_id', $warehouse->id)->whereIn('product_id', $productIds)
                ->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id');

            foreach ($order->items as $item) {
                $product = $products[$item->product_id];
                if ($order->inventory_deducted_at) {
                    $before = $balances[$item->product_id]->on_hand_quantity;
                    $product->increment('stock_quantity', $item->quantity);
                    $balances[$item->product_id]->increment('on_hand_quantity', $item->quantity);
                    StockMovement::create([
                        'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'type' => 'RETURN',
                        'quantity' => $item->quantity, 'previous_stock' => $before, 'new_stock' => $before + $item->quantity,
                        'reference' => $order->order_number, 'created_by' => $request->user()->id,
                    ]);
                } elseif ($order->inventory_reserved_at) {
                    $balances[$item->product_id]->decrement('reserved_quantity', $item->quantity);
                }
            }
        }

        $this->changeStatus($order, 'Cancelled', $request, 'Order cancelled');

        return $this->statusSuccessResponse($order);
    }

    private function shipOrder(Order $order, Request $request): void
    {
        $order->load('items.product');
        $warehouse = $order->warehouse ?: Warehouse::where('is_default', true)->firstOrFail();

        if (! $order->inventory_deducted_at) {
            $productIds = $order->items->pluck('product_id')->sort()->values();
            $balances = WarehouseInventory::where('warehouse_id', $warehouse->id)
                ->whereIn('product_id', $productIds)->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id');
            $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($order->items as $item) {
                $balance = $balances[$item->product_id] ?? null;
                if (! $balance || $balance->reserved_quantity < $item->quantity || $balance->on_hand_quantity < $item->quantity) {
                    throw new \DomainException("Reserved stock is unavailable for {$item->product->name}");
                }
            }

            foreach ($order->items as $item) {
                $balance = $balances[$item->product_id];
                $before = $balance->on_hand_quantity;
                $balance->decrement('reserved_quantity', $item->quantity);
                $balance->decrement('on_hand_quantity', $item->quantity);
                $products[$item->product_id]->decrement('stock_quantity', $item->quantity);
                StockMovement::create([
                    'product_id' => $item->product_id, 'warehouse_id' => $warehouse->id, 'type' => 'ORDER',
                    'quantity' => $item->quantity, 'previous_stock' => $before, 'new_stock' => $before - $item->quantity,
                    'reference' => $order->order_number, 'created_by' => $request->user()->id,
                ]);
            }

            $order->update(['inventory_deducted_at' => now()]);
        }

        $order->items()->update(['shipped_quantity' => DB::raw('quantity')]);
        OrderShipment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'carrier' => $request->input('carrier'),
                'tracking_number' => $request->input('tracking_number'),
                'shipping_address' => $request->input('shipping_address') ?: $order->customer?->address,
                'shipped_at' => now(),
                'created_by' => $request->user()->id,
            ],
        );
        $this->changeStatus($order, 'Shipped', $request, 'Order shipped');
    }

    private function changeStatus(Order $order, string $status, Request $request, ?string $defaultNote = null): void
    {
        $previousStatus = $order->status;
        $order->update(['status' => $status]);
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $previousStatus,
            'to_status' => $status,
            'notes' => $request->input('notes') ?: $defaultNote,
            'changed_by' => $request->user()->id,
        ]);
    }

    private function statusSuccessResponse(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'data' => $order->load(['customer', 'warehouse', 'items.product', 'shipments', 'statusHistory.user:id,name']),
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
