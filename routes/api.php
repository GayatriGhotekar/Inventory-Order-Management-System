<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderReturnController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseInventoryController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/{customer}', [CustomerController::class, 'show']);
    Route::get('/inventory', [InventoryController::class, 'index']);
    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
    Route::get('/inventory/{product}', [InventoryController::class, 'show']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::get('/warehouses', [WarehouseController::class, 'index']);
    Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show']);
    Route::get('/warehouse-inventory', [WarehouseInventoryController::class, 'index']);
    Route::get('/stock-transfers', [StockTransferController::class, 'index']);
    Route::get('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'show']);
    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index']);
    Route::get('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    Route::get('/returns', [OrderReturnController::class, 'index']);
    Route::post('/returns', [OrderReturnController::class, 'store']);
    Route::get('/returns/{orderReturn}', [OrderReturnController::class, 'show']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::prefix('reports')->group(function () {
        Route::get('/daily-sales', [ReportController::class, 'dailySales']);
        Route::get('/monthly-sales', [ReportController::class, 'monthlySales']);
        Route::get('/orders-summary', [ReportController::class, 'ordersSummary']);
        Route::get('/best-selling-products', [ReportController::class, 'bestSellingProducts']);
        Route::get('/inventory', [ReportController::class, 'inventory']);
        Route::get('/low-stock', [ReportController::class, 'lowStock']);
    });

    Route::middleware('role:admin,manager')->group(function () {
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::put('/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);

        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update']);
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy']);

        Route::post('/customers', [CustomerController::class, 'store']);
        Route::put('/customers/{customer}', [CustomerController::class, 'update']);
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy']);

        Route::post('/inventory/{product}/add', [InventoryController::class, 'addStock']);
        Route::post('/inventory/{product}/remove', [InventoryController::class, 'removeStock']);

        Route::delete('/orders/{order}', [OrderController::class, 'destroy']);
        Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::post('/warehouses', [WarehouseController::class, 'store']);
        Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update']);
        Route::delete('/warehouses/{warehouse}', [WarehouseController::class, 'destroy']);
        Route::post('/warehouse-inventory/{warehouse}/{product}/adjust', [WarehouseInventoryController::class, 'adjust']);
        Route::post('/stock-transfers', [StockTransferController::class, 'store']);
        Route::patch('/stock-transfers/{stockTransfer}/status', [StockTransferController::class, 'updateStatus']);
        Route::delete('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'destroy']);
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::patch('/purchase-orders/{purchaseOrder}/status', [PurchaseOrderController::class, 'updateStatus']);
        Route::post('/purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
        Route::delete('/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy']);
        Route::patch('/returns/{orderReturn}/status', [OrderReturnController::class, 'updateStatus']);
        Route::post('/returns/{orderReturn}/receive', [OrderReturnController::class, 'receive']);
        Route::post('/returns/{orderReturn}/refund', [OrderReturnController::class, 'refund']);
    });
});
