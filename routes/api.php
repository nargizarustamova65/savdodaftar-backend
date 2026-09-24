<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BackupController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DebtController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\Webhooks\ClickWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\PaymeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:otp');
    Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('pin', [AuthController::class, 'setPin']);
        Route::post('pin/verify', [AuthController::class, 'verifyPin'])->middleware('throttle:pin');
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('customers/{id}/history', [CustomerController::class, 'history']);
    Route::post('customers/{id}/payments', [CustomerController::class, 'pay']);
    Route::apiResource('customers', CustomerController::class)->parameters(['customers' => 'id']);

    Route::get('debts/summary', [DebtController::class, 'summary']);
    Route::post('debts/{id}/payments', [DebtController::class, 'pay']);
    Route::get('debts/{id}/audit', [DebtController::class, 'audit']);
    Route::apiResource('debts', DebtController::class)->parameters(['debts' => 'id']);

    Route::get('products/summary', [ProductController::class, 'summary']);
    Route::get('products/categories', [ProductController::class, 'categories']);
    Route::get('products/barcode/{barcode}', [ProductController::class, 'byBarcode']);
    Route::post('products/{id}/stock-in', [ProductController::class, 'stockIn']);
    Route::post('products/{id}/stock-out', [ProductController::class, 'stockOut']);
    Route::post('products/{id}/adjust', [ProductController::class, 'adjust']);
    Route::get('products/{id}/movements', [ProductController::class, 'movements']);
    Route::get('products/{id}/audit', [ProductController::class, 'audit']);
    Route::post('products/{id}/image', [ProductController::class, 'uploadImage']);
    Route::delete('products/{id}/image', [ProductController::class, 'deleteImage']);
    Route::apiResource('products', ProductController::class)->parameters(['products' => 'id']);

    Route::get('inventory/movements', [InventoryController::class, 'movements']);
    Route::post('inventory/count', [InventoryController::class, 'count']);

    Route::get('sales/summary', [SaleController::class, 'summary']);
    Route::get('sales/returns', [SaleController::class, 'returns']);
    Route::post('sales/{id}/return', [SaleController::class, 'returnSale']);
    Route::get('sales/{id}/receipt', [SaleController::class, 'receipt']);
    Route::get('sales/{id}/audit', [SaleController::class, 'audit']);
    Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show'])->parameters(['sales' => 'id']);

    Route::get('expenses/summary', [ExpenseController::class, 'summary']);
    Route::apiResource('expenses', ExpenseController::class)->only(['index', 'store', 'destroy'])->parameters(['expenses' => 'id']);
    Route::get('notifications', [NotificationController::class, 'index']);

    Route::get('dashboard', [ReportController::class, 'dashboard']);
    Route::get('reports/overview', [ReportController::class, 'overview']);
    Route::get('reports/daily', [ReportController::class, 'daily']);
    Route::get('reports/top-products', [ReportController::class, 'topProducts']);

    Route::get('billing/plan', [BillingController::class, 'plan']);
    Route::post('billing/checkout', [BillingController::class, 'checkout']);
    Route::get('billing/payments/{orderId}', [BillingController::class, 'payment']);
    Route::get('referral', [ReferralController::class, 'show']);

    Route::get('backups', [BackupController::class, 'index']);
    Route::post('backups', [BackupController::class, 'store'])->middleware('throttle:6,1');
    Route::get('backups/{id}', [BackupController::class, 'show']);
    Route::delete('backups/{id}', [BackupController::class, 'destroy']);
});

Route::post('webhooks/payme', PaymeWebhookController::class);
Route::post('webhooks/click', ClickWebhookController::class);
