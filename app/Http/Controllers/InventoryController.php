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

class InventoryController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::with('category')
            ->orderBy('name')
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Inventory retrieved successfully',
            'data' => $products,
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $movements = $product->stockMovements()
            ->with('creator:id,name')
            ->latest()
            ->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Product stock retrieved successfully',
            'data' => [
                'product' => $product->load('category'),
                'movements' => $movements,
            ],
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
            'message' => 'Low-stock products retrieved successfully',
            'data' => $products,
        ]);
    }

    public function addStock(Request $request, Product $product): JsonResponse
    {
        $validator = $this->validateStockRequest($request);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $result = DB::transaction(function () use ($request, $product, $validator) {
            $lockedProduct = Product::lockForUpdate()->findOrFail($product->id);
            $warehouse = Warehouse::where('is_default', true)->firstOrFail();
            $balance = WarehouseInventory::firstOrCreate(['warehouse_id' => $warehouse->id, 'product_id' => $product->id], ['on_hand_quantity' => $lockedProduct->stock_quantity, 'reorder_level' => $lockedProduct->low_stock_limit]);
            $balance = WarehouseInventory::lockForUpdate()->findOrFail($balance->id);
            $quantity = $validator->validated()['quantity'];
            $previousStock = $lockedProduct->stock_quantity;
            $newStock = $previousStock + $quantity;

            $lockedProduct->update(['stock_quantity' => $newStock]);
            $balance->increment('on_hand_quantity', $quantity);

            $movement = StockMovement::create([
                'product_id' => $lockedProduct->id,
                'warehouse_id' => $warehouse->id,
                'type' => 'IN',
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reference' => $validator->validated()['reference'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            return ['product' => $lockedProduct, 'movement' => $movement];
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock added successfully',
            'data' => $result,
        ]);
    }

    public function removeStock(Request $request, Product $product): JsonResponse
    {
        $validator = $this->validateStockRequest($request);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $result = DB::transaction(function () use ($request, $product, $validator) {
            $lockedProduct = Product::lockForUpdate()->findOrFail($product->id);
            $warehouse = Warehouse::where('is_default', true)->firstOrFail();
            $balance = WarehouseInventory::firstOrCreate(['warehouse_id' => $warehouse->id, 'product_id' => $product->id], ['on_hand_quantity' => $lockedProduct->stock_quantity, 'reorder_level' => $lockedProduct->low_stock_limit]);
            $balance = WarehouseInventory::lockForUpdate()->findOrFail($balance->id);
            $quantity = $validator->validated()['quantity'];

            if ($quantity > $balance->available_quantity) {
                return null;
            }

            $previousStock = $lockedProduct->stock_quantity;
            $newStock = $previousStock - $quantity;

            $lockedProduct->update(['stock_quantity' => $newStock]);
            $balance->decrement('on_hand_quantity', $quantity);

            $movement = StockMovement::create([
                'product_id' => $lockedProduct->id,
                'warehouse_id' => $warehouse->id,
                'type' => 'OUT',
                'quantity' => $quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reference' => $validator->validated()['reference'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            return ['product' => $lockedProduct, 'movement' => $movement];
        });

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock removed successfully',
            'data' => $result,
        ]);
    }

    private function validateStockRequest(Request $request)
    {
        return Validator::make($request->all(), [
            'quantity' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:255'],
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
