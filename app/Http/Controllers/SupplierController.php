<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Suppliers retrieved successfully',
            'data' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->validateSupplier($request);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $supplier = Supplier::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Supplier created successfully',
            'data' => $supplier,
        ], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Supplier retrieved successfully',
            'data' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $validator = $this->validateSupplier($request, $supplier);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $supplier->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Supplier updated successfully',
            'data' => $supplier,
        ]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier deleted successfully',
        ]);
    }

    private function validateSupplier(Request $request, ?Supplier $supplier = null)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')->ignore($supplier?->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
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
