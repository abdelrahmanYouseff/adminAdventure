<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\UserController;

Route::get('categories', [CategoryController::class, 'apiIndex']);
Route::get('products/by-category', [ProductController::class, 'apiByCategory']);
Route::get('packages', [PackageController::class, 'apiIndex']);
Route::get('products/latest', [ProductController::class, 'apiLatest']);
Route::get('customers/check-phone', [CustomerController::class, 'apiCheckPhone']);
Route::get('check-user', [UserController::class, 'apiCheckUser']);
