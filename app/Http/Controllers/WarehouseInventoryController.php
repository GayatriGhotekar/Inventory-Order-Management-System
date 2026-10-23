<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WarehouseInventoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'], 'product_id' => ['nullable', 'integer', 'exists:products,id']]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray());
        $inventory = WarehouseInventory::with(['warehouse', 'product.category'])
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->orderBy('warehouse_id')->orderBy('product_id')->paginate(20)->withQueryString();
        return response()->json(['success' => true, 'message' => 'Warehouse inventory retrieved successfully', 'data' => $inventory]);
    }

    public function adjust(Request $request, Warehouse $warehouse, Product $product): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'operation' => ['required', Rule::in(['add', 'remove', 'damage', 'restore'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'reference' => ['required', 'string', 'max:255'],
        ]);
        if ($validator->fails()) return $this->validationError($validator->errors()->toArray());

        try {
            $balance = DB::transaction(function () use ($request, $warehouse, $product, $validator) {
                $balance = WarehouseInventory::whereBelongsTo($warehouse)->whereBelongsTo($product)->lockForUpdate()->first();
                if (! $balance) {
                    $balance = WarehouseInventory::create(['warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'reorder_level' => $product->low_stock_limit]);
                    $balance = WarehouseInventory::lockForUpdate()->findOrFail($balance->id);
                }
                $data = $validator->validated();
                $quantity = $data['quantity'];
                $before = $balance->on_hand_quantity;
                if (in_array($data['operation'], ['remove', 'damage'], true) && $balance->available_quantity < $quantity) throw new \DomainException('Insufficient available stock in this warehouse');
                if ($data['operation'] === 'restore' && $balance->damaged_quantity < $quantity) throw new \DomainException('Insufficient damaged stock to restore');

                match ($data['operation']) {
                    'add' => $balance->on_hand_quantity += $quantity,
                    'remove' => $balance->on_hand_quantity -= $quantity,
                    'damage' => $balance->damaged_quantity += $quantity,
                    'restore' => $balance->damaged_quantity -= $quantity,
                };
                $balance->save();
                if (in_array($data['operation'], ['add', 'remove'], true)) {
                    $lockedProduct = $product->newQuery()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                    $data['operation'] === 'add'
                        ? $lockedProduct->increment('stock_quantity', $quantity)
                        : $lockedProduct->decrement('stock_quantity', $quantity);
                }
                StockMovement::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'type' => in_array($data['operation'], ['add', 'restore'], true) ? 'IN' : 'OUT', 'quantity' => $quantity, 'previous_stock' => $before, 'new_stock' => $balance->on_hand_quantity, 'reference' => $data['reference'], 'created_by' => $request->user()->id]);
                return $balance;
            });
        } catch (\DomainException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
        return response()->json(['success' => true, 'message' => 'Warehouse inventory adjusted successfully', 'data' => $balance->load(['warehouse', 'product'])]);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 422);
    }
}
