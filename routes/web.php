<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PackageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;

use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

Route::get('dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');


Route::get('products', [ProductController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('products');

Route::get('products/create', [ProductController::class, 'create'])
    ->middleware(['auth', 'verified'])
    ->name('products.create');

Route::post('products', [ProductController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('products.store');

Route::get('products/{product}/edit', [ProductController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('products.edit');

Route::put('products/{product}', [ProductController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('products.update');

Route::patch('products/{product}', [ProductController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('products.patch');

Route::delete('products/{product}', [ProductController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('products.destroy');

Route::get('categories', [CategoryController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('categories.index');

Route::get('categories/create', [CategoryController::class, 'create'])
    ->middleware(['auth', 'verified'])
    ->name('categories.create');

Route::post('categories', [CategoryController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('categories.store');

Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('categories.edit');

Route::put('categories/{category}', [CategoryController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('categories.update');

Route::delete('categories/{category}', [CategoryController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('categories.destroy');

Route::get('customers', [\App\Http\Controllers\CustomerController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('customers');

Route::get('orders', [\App\Http\Controllers\OrderController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('orders');

Route::get('users', [\App\Http\Controllers\UserController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('users');

// Package routes
Route::resource('packages', PackageController::class)->middleware(['auth', 'verified']);

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
