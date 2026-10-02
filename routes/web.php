<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::view('/api/documentation', 'api-documentation');

Route::get('/api/health', function () {
    try {
        DB::select('SELECT 1');

        return response()->json([
            'success' => true,
            'message' => 'Laravel and MySQL are working',
        ]);
    } catch (Throwable $exception) {
        return response()->json([
            'success' => false,
            'message' => 'Laravel is running, but MySQL is unavailable',
        ], 503);
    }
});
