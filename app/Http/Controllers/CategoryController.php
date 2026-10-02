<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully',
            'data' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->validateCategory($request);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $category = Category::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Category retrieved successfully',
            'data' => $category,
        ]);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validator = $this->validateCategory($request, $category);

        if ($validator->fails()) {
            return $this->validationError($validator->errors()->toArray());
        }

        $category->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category,
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Category cannot be deleted because it has products',
            ], 409);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    private function validateCategory(Request $request, ?Category $category = null)
    {
        return Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->ignore($category?->id),
            ],
            'description' => ['nullable', 'string'],
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
