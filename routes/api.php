<?php

use App\Http\Controllers\Api\Admin\CategoryController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\ProductPackageController;
use App\Http\Controllers\Api\PublicProductController;
use App\Http\Controllers\Api\PublicCategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\Admin\SubscriptionController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\SliderController;



// public
Route::post('/admin/login', [AuthController::class, 'login']);
Route::get('/products', [PublicProductController::class, 'index']);
Route::get('/products/{slug}', [PublicProductController::class, 'show']);
Route::get('/categories', [PublicCategoryController::class, 'index']);
Route::get('/categories/{slug}', [PublicCategoryController::class, 'show']);
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/homepage-data', [PublicProductController::class, 'homepageData']);

Route::get('/payment/success', [OrderController::class, 'paymentSuccess']);
Route::get('/payment/fail', [OrderController::class, 'paymentFail']);
Route::get('/payment/cancel', [OrderController::class, 'paymentCancel']);




// protected
Route::middleware('auth:sanctum')->group(function () {
  Route::get('/admin/me', [AuthController::class, 'me']);
  Route::post('/admin/logout', [AuthController::class, 'logout']);


  // Category CRUD
  Route::get('/admin/categories', [CategoryController::class, 'index']);
  Route::post('/admin/categories', [CategoryController::class, 'store']);
  Route::get('/admin/categories/{category}', [CategoryController::class, 'show']);
  Route::post('/admin/categories/{category}', [CategoryController::class, 'update']);
  Route::delete('/admin/category/{category}', [CategoryController::class, 'destroy']);


  // Product CRUD
  Route::get('/admin/products', [ProductController::class, 'index']);
  Route::post('/admin/products', [ProductController::class, 'store']);
  Route::get('/admin/products/{product}', [ProductController::class, 'show']);
  Route::put('/admin/products/{product}', [ProductController::class, 'update']);
  Route::delete('/admin/products/{product}', [ProductController::class, 'destroy']);


  // Product Package CRUD
  Route::get('/admin/product-packages', [ProductPackageController::class, 'index']);
  Route::post('/admin/product-packages', [ProductPackageController::class, 'store']);
  Route::get('/admin/product-packages/{productPackage}', [ProductPackageController::class, 'show']);
  Route::post('/admin/product-packages/{productPackage}', [ProductPackageController::class, 'update']);
  Route::delete('/admin/product-packages/{productPackage}', [ProductPackageController::class, 'destroy']);


  // Admin Order Management
  Route::get('/admin/orders', [AdminOrderController::class, 'index']);
  Route::get('/admin/orders/{order}', [AdminOrderController::class, 'show']);
  Route::post('/admin/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
  Route::post('/admin/orders/{order}/create-subscriptions', [AdminOrderController::class, 'createSubscriptions']);


  // Subscription Management
  Route::get('/admin/subscriptions', [SubscriptionController::class, 'index']);
  Route::get('/admin/subscriptions/{subscription}', [SubscriptionController::class, 'show']);
  Route::post('/admin/subscriptions/{subscription}', [SubscriptionController::class, 'update']);
  Route::post('/admin/subscriptions/{subscription}/status', [SubscriptionController::class, 'updateStatus']);

  // Slider Management
  Route::post('/admin/sliders/reorder', [SliderController::class, 'updateOrder']); // <--- Add this FIRST
  Route::get('/admin/sliders', [SliderController::class, 'index']);
  Route::post('/admin/sliders', [SliderController::class, 'store']);
  Route::delete('/admin/sliders/{slider}', [SliderController::class, 'destroy']);



  Route::get('/admin/dashboard', [DashboardController::class, 'index']);








});
