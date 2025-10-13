<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PaymentController;

Route::get('categories', [CategoryController::class, 'apiIndex']);
Route::get('products/by-category', [ProductController::class, 'apiByCategory']);
Route::get('packages', [PackageController::class, 'apiIndex']);
Route::get('products/latest', [ProductController::class, 'apiLatest']);
Route::get('customers/check-phone', [CustomerController::class, 'apiCheckPhone']);
Route::get('check-user', [UserController::class, 'apiCheckUser']);
Route::get('user/by-phone', [UserController::class, 'getUserByPhone']);
Route::post('register', [UserController::class, 'apiRegister']);

// Payment Routes
Route::post('payment/create', [PaymentController::class, 'createPaymentSession']);
Route::get('payment/status', [PaymentController::class, 'getPaymentStatus']);
Route::get('payment/success', [PaymentController::class, 'paymentSuccess']);
Route::get('payment/cancel', [PaymentController::class, 'paymentCancel']);
Route::post('payment/webhook', [PaymentController::class, 'webhook']);
