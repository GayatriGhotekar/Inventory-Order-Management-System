<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::with('category')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $query->where('category_id', $request->integer('category_id'));
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => $products,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->validateProduct($request);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $product = DB::transaction(function () use ($request, $validator) {
            $product = Product::create($validator->validated());

            if ($product->stock_quantity > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'IN',
                    'quantity' => $product->stock_quantity,
                    'previous_stock' => 0,
                    'new_stock' => $product->stock_quantity,
                    'reference' => 'Initial stock',
                    'created_by' => $request->user()->id,
                ]);
            }

            return $product;
        });

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load('category'),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully',
            'data' => $product->load('category'),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validator = $this->validateProduct($request, $product);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $product = DB::transaction(function () use ($request, $product, $validator) {
            $lockedProduct = Product::lockForUpdate()->findOrFail($product->id);
            $previousStock = $lockedProduct->stock_quantity;

            $lockedProduct->update($validator->validated());

            if ($previousStock !== $lockedProduct->stock_quantity) {
                StockMovement::create([
                    'product_id' => $lockedProduct->id,
                    'type' => 'ADJUSTMENT',
                    'quantity' => abs($lockedProduct->stock_quantity - $previousStock),
                    'previous_stock' => $previousStock,
                    'new_stock' => $lockedProduct->stock_quantity,
                    'reference' => 'Product stock updated',
                    'created_by' => $request->user()->id,
                ]);
            }

            return $lockedProduct;
        });

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->load('category'),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    private function validateProduct(Request $request, ?Product $product = null)
    {
        return Validator::make($request->all(), [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:255',
                Rule::unique('products', 'sku')->ignore($product?->id),
            ],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'low_stock_limit' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
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
