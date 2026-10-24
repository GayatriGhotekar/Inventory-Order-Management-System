<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['nullable', Rule::in(['Draft', 'Approved', 'Partially Received', 'Received', 'Cancelled'])],
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray());

        $orders = PurchaseOrder::with(['supplier', 'warehouse', 'creator:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()->paginate(15)->withQueryString();

        return response()->json(['success' => true, 'message' => 'Purchase orders retrieved successfully', 'data' => $orders]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'expected_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray());
        $data = $validator->validated();

        if (! Supplier::whereKey($data['supplier_id'])->where('status', 'Active')->exists()) {
            return response()->json(['success' => false, 'message' => 'The supplier must be active'], 422);
        }
        if (! Warehouse::whereKey($data['warehouse_id'])->where('status', 'Active')->exists()) {
            return response()->json(['success' => false, 'message' => 'The warehouse must be active'], 422);
        }

        $purchaseOrder = DB::transaction(function () use ($request, $data) {
            $total = collect($data['items'])->sum(fn ($item) => round($item['quantity'] * $item['unit_cost'], 2));
            $purchaseOrder = PurchaseOrder::create([
                'purchase_order_number' => 'PO-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'supplier_id' => $data['supplier_id'], 'warehouse_id' => $data['warehouse_id'],
                'expected_date' => $data['expected_date'] ?? null, 'notes' => $data['notes'] ?? null,
                'status' => 'Draft', 'total' => $total, 'created_by' => $request->user()->id,
            ]);
            foreach ($data['items'] as $item) {
                $purchaseOrder->items()->create([
                    'product_id' => $item['product_id'], 'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'], 'total_cost' => round($item['quantity'] * $item['unit_cost'], 2),
                ]);
            }
            return $purchaseOrder;
        });

        return response()->json(['success' => true, 'message' => 'Purchase order created successfully', 'data' => $this->loadOrder($purchaseOrder)], 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Purchase order retrieved successfully', 'data' => $this->loadOrder($purchaseOrder)]);
    }

    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $validator = Validator::make($request->all(), ['status' => ['required', Rule::in(['Approved', 'Cancelled'])]]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray());
        $newStatus = $validator->validated()['status'];

        if ($newStatus === 'Approved' && $purchaseOrder->status !== 'Draft') {
            return response()->json(['success' => false, 'message' => 'Only a draft purchase order can be approved'], 409);
        }
        if ($newStatus === 'Cancelled' && ! in_array($purchaseOrder->status, ['Draft', 'Approved'], true)) {
            return response()->json(['success' => false, 'message' => "A {$purchaseOrder->status} purchase order cannot be cancelled"], 409);
        }

        $purchaseOrder->update($newStatus === 'Approved'
            ? ['status' => 'Approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]
            : ['status' => 'Cancelled']);

        return response()->json(['success' => true, 'message' => 'Purchase order status updated successfully', 'data' => $this->loadOrder($purchaseOrder)]);
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (! in_array($purchaseOrder->status, ['Approved', 'Partially Received'], true)) {
            return response()->json(['success' => false, 'message' => 'Only approved purchase orders can receive goods'], 409);
        }

        $validator = Validator::make($request->all(), [
            'supplier_invoice_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.accepted_quantity' => ['required', 'integer', 'min:0'],
            'items.*.damaged_quantity' => ['required', 'integer', 'min:0'],
            'items.*.rejected_quantity' => ['required', 'integer', 'min:0'],
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray());

        try {
            $receipt = DB::transaction(function () use ($request, $purchaseOrder, $validator) {
                $order = PurchaseOrder::lockForUpdate()->findOrFail($purchaseOrder->id);
                if (! in_array($order->status, ['Approved', 'Partially Received'], true)) throw new \DomainException('This purchase order can no longer receive goods');

                $receipt = GoodsReceipt::create([
                    'receipt_number' => 'GRN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                    'purchase_order_id' => $order->id,
                    'supplier_invoice_reference' => $validator->validated()['supplier_invoice_reference'] ?? null,
                    'notes' => $validator->validated()['notes'] ?? null,
                    'received_by' => $request->user()->id,
                ]);

                foreach ($validator->validated()['items'] as $received) {
                    $item = PurchaseOrderItem::where('purchase_order_id', $order->id)
                        ->whereKey($received['purchase_order_item_id'])->lockForUpdate()->first();
                    if (! $item) throw new \DomainException('A received item does not belong to this purchase order');

                    $processed = $received['accepted_quantity'] + $received['damaged_quantity'] + $received['rejected_quantity'];
                    $remaining = $item->quantity - $item->received_quantity;
                    if ($processed < 1) throw new \DomainException('Each receipt item must contain at least one unit');
                    if ($processed > $remaining) throw new \DomainException("Received quantity exceeds the remaining quantity for {$item->product->name}");

                    $receipt->items()->create($received);
                    $item->increment('received_quantity', $processed);

                    if ($received['accepted_quantity'] > 0) {
                        $balance = WarehouseInventory::firstOrCreate(
                            ['warehouse_id' => $order->warehouse_id, 'product_id' => $item->product_id],
                            ['reorder_level' => $item->product->low_stock_limit],
                        );
                        $balance = WarehouseInventory::lockForUpdate()->findOrFail($balance->id);
                        $before = $balance->on_hand_quantity;
                        $balance->increment('on_hand_quantity', $received['accepted_quantity']);
                        Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail()->increment('stock_quantity', $received['accepted_quantity']);
                        StockMovement::create([
                            'product_id' => $item->product_id, 'warehouse_id' => $order->warehouse_id, 'type' => 'IN',
                            'quantity' => $received['accepted_quantity'], 'previous_stock' => $before,
                            'new_stock' => $before + $received['accepted_quantity'],
                            'reference' => $receipt->receipt_number, 'created_by' => $request->user()->id,
                        ]);
                    }
                }

                $allReceived = ! $order->items()->whereColumn('received_quantity', '<', 'quantity')->exists();
                $order->update(['status' => $allReceived ? 'Received' : 'Partially Received']);
                return $receipt;
            });
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => 'Goods received successfully', 'data' => $receipt->load(['receiver:id,name', 'items.purchaseOrderItem.product'])], 201);
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== 'Draft') return response()->json(['success' => false, 'message' => 'Only draft purchase orders can be deleted'], 409);
        $purchaseOrder->delete();
        return response()->json(['success' => true, 'message' => 'Purchase order deleted successfully']);
    }

    private function loadOrder(PurchaseOrder $order): PurchaseOrder
    {
        return $order->load(['supplier', 'warehouse', 'creator:id,name', 'approver:id,name', 'items.product', 'receipts.receiver:id,name', 'receipts.items']);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 422);
    }
}
