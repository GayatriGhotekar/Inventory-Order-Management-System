<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Customers retrieved successfully',
            'data' => Customer::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->validateCustomer($request);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $customer = Customer::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data' => $customer,
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Customer retrieved successfully',
            'data' => $customer,
        ]);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validator = $this->validateCustomer($request, $customer);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $customer->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer,
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ]);
    }

    private function validateCustomer(Request $request, ?Customer $customer = null)
    {
        return Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customer?->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
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
