<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Warehouses retrieved successfully', 'data' => Warehouse::orderByDesc('is_default')->orderBy('name')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->validateWarehouse($request);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        if (($validator->validated()['is_default'] ?? false) === true) {
            return response()->json(['success' => false, 'message' => 'The default warehouse designation cannot be changed during the compatibility phase'], 409);
        }

        $warehouse = Warehouse::create($validator->validated());

        return response()->json(['success' => true, 'message' => 'Warehouse created successfully', 'data' => $warehouse], 201);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return response()->json(['success' => true, 'message' => 'Warehouse retrieved successfully', 'data' => $warehouse->loadCount('inventories')]);
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $validator = $this->validateWarehouse($request, $warehouse);
        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }
        $data = $validator->validated();
        if (array_key_exists('is_default', $data) && (bool) $data['is_default'] !== $warehouse->is_default) {
            return response()->json(['success' => false, 'message' => 'The default warehouse designation cannot be changed during the compatibility phase'], 409);
        }
        $warehouse->update($data);

        return response()->json(['success' => true, 'message' => 'Warehouse updated successfully', 'data' => $warehouse->fresh()]);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        if ($warehouse->is_default) {
            return response()->json(['success' => false, 'message' => 'The default warehouse cannot be deleted'], 409);
        }
        if ($warehouse->inventories()->where(function ($query) {
            $query->where('on_hand_quantity', '>', 0)->orWhere('reserved_quantity', '>', 0)->orWhere('damaged_quantity', '>', 0);
        })->exists()) {
            return response()->json(['success' => false, 'message' => 'Warehouse cannot be deleted while it contains stock'], 409);
        }
        if ($warehouse->outgoingTransfers()->exists() || $warehouse->incomingTransfers()->exists()) {
            return response()->json(['success' => false, 'message' => 'Warehouse cannot be deleted because it has transfer history'], 409);
        }
        $warehouse->delete();

        return response()->json(['success' => true, 'message' => 'Warehouse deleted successfully']);
    }

    private function validateWarehouse(Request $request, ?Warehouse $warehouse = null)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('warehouses', 'code')->ignore($warehouse?->id)],
            'address' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);
    }

    private function validationError(array $errors): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 422);
    }
}
