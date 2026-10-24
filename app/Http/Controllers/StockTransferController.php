<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StockTransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['status' => ['nullable', Rule::in(['Draft', 'Approved', 'In Transit', 'Received', 'Cancelled'])]]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $transfers = StockTransfer::with(['sourceWarehouse', 'destinationWarehouse', 'creator:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()->paginate(15)->withQueryString();

        return response()->json(['success' => true, 'message' => 'Stock transfers retrieved successfully', 'data' => $transfers]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:destination_warehouse_id'],
            'destination_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $data = $validator->validated();
        if (! Warehouse::whereKey($data['source_warehouse_id'])->where('status', 'Active')->exists() || ! Warehouse::whereKey($data['destination_warehouse_id'])->where('status', 'Active')->exists()) {
            return response()->json(['success' => false, 'message' => 'Both warehouses must be active'], 422);
        }
        $transfer = DB::transaction(function () use ($request, $data) {
            $transfer = StockTransfer::create([
                'transfer_number' => 'TRF-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'source_warehouse_id' => $data['source_warehouse_id'], 'destination_warehouse_id' => $data['destination_warehouse_id'],
                'status' => 'Draft', 'notes' => $data['notes'] ?? null, 'created_by' => $request->user()->id,
            ]);
            foreach ($data['items'] as $item) {
                $transfer->items()->create($item);
            }

            return $transfer;
        });

        return response()->json(['success' => true, 'message' => 'Stock transfer created successfully', 'data' => $this->loadTransfer($transfer)], 201);
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Stock transfer retrieved successfully', 'data' => $this->loadTransfer($stockTransfer)]);
    }

    public function updateStatus(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        $validator = Validator::make($request->all(), ['status' => ['required', Rule::in(['Approved', 'In Transit', 'Received', 'Cancelled'])]]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        try {
            $transfer = DB::transaction(function () use ($request, $stockTransfer, $validator) {
                $transfer = StockTransfer::lockForUpdate()->findOrFail($stockTransfer->id);
                $newStatus = $validator->validated()['status'];
                if ($newStatus === 'Cancelled') {
                    if (! in_array($transfer->status, ['Draft', 'Approved'], true)) {
                        throw new \DomainException("A {$transfer->status} transfer cannot be cancelled");
                    }
                    $transfer->update(['status' => 'Cancelled']);

                    return $transfer;
                }
                $expected = ['Draft' => 'Approved', 'Approved' => 'In Transit', 'In Transit' => 'Received'][$transfer->status] ?? null;
                if ($expected !== $newStatus) {
                    throw new \DomainException("Transfer cannot move from {$transfer->status} to {$newStatus}");
                }
                if ($newStatus === 'Approved') {
                    $this->ensureSourceStock($transfer);
                    $transfer->update(['status' => 'Approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
                } elseif ($newStatus === 'In Transit') {
                    $this->dispatch($transfer, $request->user()->id);
                    $transfer->update(['status' => 'In Transit', 'dispatched_at' => now()]);
                } else {
                    $this->receive($transfer, $request->user()->id);
                    $transfer->update(['status' => 'Received', 'received_by' => $request->user()->id, 'received_at' => now()]);
                }

                return $transfer;
            });
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 409);
        }

        return response()->json(['success' => true, 'message' => 'Stock transfer status updated successfully', 'data' => $this->loadTransfer($transfer)]);
    }

    public function destroy(StockTransfer $stockTransfer): JsonResponse
    {
        if ($stockTransfer->status !== 'Draft') {
            return response()->json(['success' => false, 'message' => 'Only draft transfers can be deleted'], 409);
        }
        $stockTransfer->delete();

        return response()->json(['success' => true, 'message' => 'Stock transfer deleted successfully']);
    }

    private function ensureSourceStock(StockTransfer $transfer): void
    {
        $transfer->load('items.product');
        foreach ($transfer->items->sortBy('product_id') as $item) {
            $balance = WarehouseInventory::where('warehouse_id', $transfer->source_warehouse_id)->where('product_id', $item->product_id)->lockForUpdate()->first();
            if (! $balance || $balance->available_quantity < $item->quantity) {
                throw new \DomainException("Insufficient available stock for {$item->product->name}");
            }
        }
    }

    private function dispatch(StockTransfer $transfer, int $userId): void
    {
        $this->ensureSourceStock($transfer);
        foreach ($transfer->items->sortBy('product_id') as $item) {
            $balance = WarehouseInventory::where('warehouse_id', $transfer->source_warehouse_id)->where('product_id', $item->product_id)->lockForUpdate()->firstOrFail();
            $before = $balance->on_hand_quantity;
            $balance->decrement('on_hand_quantity', $item->quantity);
            Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail()->decrement('stock_quantity', $item->quantity);
            StockMovement::create(['product_id' => $item->product_id, 'warehouse_id' => $transfer->source_warehouse_id, 'type' => 'OUT', 'quantity' => $item->quantity, 'previous_stock' => $before, 'new_stock' => $before - $item->quantity, 'reference' => $transfer->transfer_number.' dispatched', 'created_by' => $userId]);
        }
    }

    private function receive(StockTransfer $transfer, int $userId): void
    {
        $transfer->load('items');
        foreach ($transfer->items->sortBy('product_id') as $item) {
            $balance = WarehouseInventory::firstOrCreate(['warehouse_id' => $transfer->destination_warehouse_id, 'product_id' => $item->product_id], ['reorder_level' => $item->product->low_stock_limit]);
            $balance = WarehouseInventory::lockForUpdate()->findOrFail($balance->id);
            $before = $balance->on_hand_quantity;
            $balance->increment('on_hand_quantity', $item->quantity);
            Product::whereKey($item->product_id)->lockForUpdate()->firstOrFail()->increment('stock_quantity', $item->quantity);
            $item->update(['received_quantity' => $item->quantity]);
            StockMovement::create(['product_id' => $item->product_id, 'warehouse_id' => $transfer->destination_warehouse_id, 'type' => 'IN', 'quantity' => $item->quantity, 'previous_stock' => $before, 'new_stock' => $before + $item->quantity, 'reference' => $transfer->transfer_number.' received', 'created_by' => $userId]);
        }
    }

    private function loadTransfer(StockTransfer $transfer): StockTransfer
    {
        return $transfer->load(['sourceWarehouse', 'destinationWarehouse', 'creator:id,name', 'approver:id,name', 'receiver:id,name', 'items.product']);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 422);
    }
}
