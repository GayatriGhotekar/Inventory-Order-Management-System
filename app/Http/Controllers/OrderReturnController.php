<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderReturn;
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

class OrderReturnController extends Controller
{
    private const STATUSES = ['Requested', 'Approved', 'Received', 'Refunded', 'Rejected'];

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['status' => ['nullable', Rule::in(self::STATUSES)]]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $returns = OrderReturn::with(['order.customer', 'requester:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()->paginate(15)->withQueryString();

        return response()->json(['success' => true, 'message' => 'Returns retrieved successfully', 'data' => $returns]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $data = $validator->validated();
        $order = Order::with('items')->findOrFail($data['order_id']);
        if ($order->status !== 'Delivered') {
            return response()->json(['success' => false, 'message' => 'Only delivered orders can be returned'], 409);
        }

        try {
            $return = DB::transaction(function () use ($request, $data, $order) {
                $return = OrderReturn::create([
                    'return_number' => 'RET-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                    'order_id' => $order->id, 'status' => 'Requested', 'reason' => $data['reason'],
                    'notes' => $data['notes'] ?? null, 'requested_by' => $request->user()->id,
                ]);
                $refundTotal = 0;
                foreach ($data['items'] as $requestedItem) {
                    $orderItem = $order->items->firstWhere('id', $requestedItem['order_item_id']);
                    if (! $orderItem) {
                        throw new \DomainException('A return item does not belong to this order');
                    }
                    $alreadyReturned = $orderItem->returnItems()
                        ->whereHas('orderReturn', fn ($query) => $query->where('status', '!=', 'Rejected'))
                        ->sum('quantity');
                    if ($alreadyReturned + $requestedItem['quantity'] > $orderItem->shipped_quantity) {
                        throw new \DomainException("Return quantity exceeds the returnable quantity for {$orderItem->product->name}");
                    }
                    $amount = round((float) $orderItem->unit_price * $requestedItem['quantity'], 2);
                    $return->items()->create([
                        'order_item_id' => $orderItem->id, 'quantity' => $requestedItem['quantity'],
                        'condition' => 'Pending', 'unit_refund' => $orderItem->unit_price, 'refund_amount' => $amount,
                    ]);
                    $refundTotal += $amount;
                }
                $return->update(['refund_amount' => $refundTotal]);

                return $return;
            });
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Return requested successfully', 'data' => $this->loadReturn($return)], 201);
    }

    public function show(OrderReturn $orderReturn): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Return retrieved successfully', 'data' => $this->loadReturn($orderReturn)]);
    }

    public function updateStatus(Request $request, OrderReturn $orderReturn): JsonResponse
    {
        $validator = Validator::make($request->all(), ['status' => ['required', Rule::in(['Approved', 'Rejected'])], 'notes' => ['nullable', 'string']]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        if ($orderReturn->status !== 'Requested') {
            return response()->json(['success' => false, 'message' => 'Only requested returns can be approved or rejected'], 409);
        }

        $status = $validator->validated()['status'];
        $orderReturn->update([
            'status' => $status,
            'notes' => $validator->validated()['notes'] ?? $orderReturn->notes,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => "Return {$status} successfully", 'data' => $this->loadReturn($orderReturn)]);
    }

    public function receive(Request $request, OrderReturn $orderReturn): JsonResponse
    {
        if ($orderReturn->status !== 'Approved') {
            return response()->json(['success' => false, 'message' => 'Only approved returns can be received'], 409);
        }
        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_return_item_id' => ['required', 'integer', 'distinct'],
            'items.*.condition' => ['required', Rule::in(['Restock', 'Damaged'])],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        try {
            $return = DB::transaction(function () use ($request, $orderReturn, $validator) {
                $return = OrderReturn::lockForUpdate()->findOrFail($orderReturn->id);
                if ($return->status !== 'Approved') {
                    throw new \DomainException('This return can no longer be received');
                }
                $return->load(['order.warehouse', 'items.orderItem.product']);
                if (count($validator->validated()['items']) !== $return->items->count()) {
                    throw new \DomainException('Inspect every return item before receiving it');
                }
                $warehouse = $return->order->warehouse ?: Warehouse::where('is_default', true)->firstOrFail();

                foreach ($validator->validated()['items'] as $inspected) {
                    $returnItem = $return->items->firstWhere('id', $inspected['order_return_item_id']);
                    if (! $returnItem) {
                        throw new \DomainException('An inspected item does not belong to this return');
                    }
                    $product = Product::whereKey($returnItem->orderItem->product_id)->lockForUpdate()->firstOrFail();
                    $balance = WarehouseInventory::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
                    $before = $balance->on_hand_quantity;
                    $balance->increment('on_hand_quantity', $returnItem->quantity);
                    if ($inspected['condition'] === 'Damaged') {
                        $balance->increment('damaged_quantity', $returnItem->quantity);
                    }
                    $product->increment('stock_quantity', $returnItem->quantity);
                    $returnItem->update(['condition' => $inspected['condition']]);
                    StockMovement::create([
                        'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'type' => 'RETURN',
                        'quantity' => $returnItem->quantity, 'previous_stock' => $before,
                        'new_stock' => $before + $returnItem->quantity,
                        'reference' => $return->return_number, 'created_by' => $request->user()->id,
                    ]);
                }

                $return->update(['status' => 'Received', 'received_by' => $request->user()->id, 'received_at' => now()]);

                return $return;
            });
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Returned goods received successfully', 'data' => $this->loadReturn($return)]);
    }

    public function refund(Request $request, OrderReturn $orderReturn): JsonResponse
    {
        if ($orderReturn->status !== 'Received') {
            return response()->json(['success' => false, 'message' => 'Only received returns can be refunded'], 409);
        }
        $orderReturn->update(['status' => 'Refunded', 'refunded_by' => $request->user()->id, 'refunded_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Refund recorded successfully', 'data' => $this->loadReturn($orderReturn)]);
    }

    private function loadReturn(OrderReturn $return): OrderReturn
    {
        return $return->load(['order.customer', 'order.warehouse', 'requester:id,name', 'approver:id,name', 'receiver:id,name', 'refunder:id,name', 'items.orderItem.product']);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 422);
    }
}
