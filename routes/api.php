<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GenderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // User management (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::get('/roles', [UserController::class, 'getRoles']);
    });

    // Gender routes
    Route::get('/genders', [GenderController::class, 'loadGenders']);
    Route::get('/genders/{genderId}', [GenderController::class, 'getGender']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/genders', [GenderController::class, 'storeGender']);
        Route::put('/genders/{gender}', [GenderController::class, 'updateGender']);
        Route::delete('/genders/{gender}', [GenderController::class, 'destroyGender']);
    });

    // Product management
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}/stock', [ProductController::class, 'updateStock']);
    Route::get('/products/low-stock/list', [ProductController::class, 'getLowStockProducts']);

    // Category management
    Route::apiResource('categories', CategoryController::class);

    // Customer management
    Route::apiResource('customers', CustomerController::class);

    // Transaction management
    Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'show']);
    Route::get('/transactions/daily-sales', [TransactionController::class, 'getDailySales']);

    // Discount management
    Route::apiResource('discounts', DiscountController::class);
    Route::post('/discounts/validate', [DiscountController::class, 'validateCode']);

    // Feedback management
    Route::apiResource('feedback', FeedbackController::class)->only(['index', 'store', 'show']);
    Route::get('/feedback/statistics', [FeedbackController::class, 'getStatistics']);

    // Reports (Manager and Admin only)
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('/reports/sales', [ReportController::class, 'salesReport']);
        Route::get('/reports/products', [ReportController::class, 'productReport']);
        Route::get('/reports/customers', [ReportController::class, 'customerReport']);
        Route::get('/reports/feedback', [ReportController::class, 'feedbackReport']);
        Route::get('/reports/dashboard', [ReportController::class, 'dashboardStats']);
    });
});