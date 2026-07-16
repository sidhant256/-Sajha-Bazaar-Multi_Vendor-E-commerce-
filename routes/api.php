<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\VendorController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register/customer', [AuthController::class, 'registerCustomer']);
Route::post('/register/vendor', [AuthController::class, 'registerVendor']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);


Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-products', [ProductController::class, 'mine']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
});

Route::get('/vendors', [VendorController::class, 'index']);
Route::get('/vendors/{vendor}', [VendorController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/vendors/{vendor}', [VendorController::class, 'update']);
    Route::post('/vendors/{vendor}/approve',[VendorController::class, 'approve']);
    Route::post('/vendors/{vendor}/suspend',[VendorController::class, 'suspend']);
});