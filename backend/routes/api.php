<?php

use App\Http\Controllers\Api\AgReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BuildingController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FundCallController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\LotAccessController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\LotOwnerController;
use App\Http\Controllers\Api\LotReferenceController;
use App\Http\Controllers\Api\LotTypeController;
use App\Http\Controllers\Api\LotTypeRateController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Platform\SubscriptionsController as PlatformSubscriptionsController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ResidenceController;
use App\Http\Controllers\Api\RevenueCategoryController;
use App\Http\Controllers\Api\RevenueController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TreasuryReportController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');

    Route::middleware(['verified', 'tenant.user'])->group(function () {
        Route::get('/residence', [ResidenceController::class, 'show']);
        Route::get('/buildings', [BuildingController::class, 'index']);
        Route::get('/lot-types', [LotTypeController::class, 'index']);
        Route::get('/lots', [LotController::class, 'index']);
        Route::get('/lots/{lot}/owners', [LotOwnerController::class, 'index']);
        Route::get('/lots/{lot}/references', [LotReferenceController::class, 'index']);

        Route::get('/fund-calls/unpaid', [FundCallController::class, 'unpaid']);
        Route::get('/fund-calls/matrix', [FundCallController::class, 'matrix']);
        Route::get('/fund-calls/{fundCall}', [FundCallController::class, 'show']);
        Route::get('/fund-calls', [FundCallController::class, 'index']);
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/fund-calls/{fundCall}/payments/{payment}/receipt', [PaymentController::class, 'receipt']);
        Route::get('/payment-batches/{batchId}/receipt', [PaymentController::class, 'batchReceipt']);

        Route::get('/expenses', [ExpenseController::class, 'index']);
        Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'receipt']);
        Route::get('/expense-categories', [ExpenseCategoryController::class, 'index']);

        Route::get('/revenues', [RevenueController::class, 'index']);
        Route::get('/revenues/{revenue}/receipt', [RevenueController::class, 'receipt']);
        Route::get('/revenue-categories', [RevenueCategoryController::class, 'index']);

        Route::get('/treasury-report', [TreasuryReportController::class, 'index']);
        Route::get('/ledger', [LedgerController::class, 'index']);
        Route::get('/reports/payments', [ReportController::class, 'payments']);
        Route::get('/reports/ag', [AgReportController::class, 'index']);
        Route::get('/subscription', [SubscriptionController::class, 'show']);
        Route::get('/subscription/invoices', [SubscriptionController::class, 'invoices']);

        Route::middleware('subscription.active')->group(function () {
            Route::middleware(['admin'])->group(function () {
                Route::put('/residence', [ResidenceController::class, 'update']);
                Route::get('/role-permissions', [RolePermissionController::class, 'index']);
                Route::put('/role-permissions', [RolePermissionController::class, 'update']);

                Route::get('/users', [UserController::class, 'index']);
                Route::post('/users', [UserController::class, 'store']);
                Route::delete('/users/{user}', [UserController::class, 'destroy']);

                Route::post('/lots/{lot}/access', [LotAccessController::class, 'store']);
            });

            Route::middleware(['permission:immeubles.gerer'])->group(function () {
                Route::post('/buildings', [BuildingController::class, 'store']);
                Route::put('/buildings/{building}', [BuildingController::class, 'update']);
                Route::delete('/buildings/{building}', [BuildingController::class, 'destroy']);
            });

            Route::middleware(['permission:types_lot.gerer'])->group(function () {
                Route::post('/lot-types', [LotTypeController::class, 'store']);
                Route::put('/lot-types/{lotType}', [LotTypeController::class, 'update']);
                Route::delete('/lot-types/{lotType}', [LotTypeController::class, 'destroy']);

                Route::post('/lot-types/{lotType}/rates', [LotTypeRateController::class, 'store']);
                Route::delete('/lot-types/{lotType}/rates/{rate}', [LotTypeRateController::class, 'destroy']);
            });

            Route::middleware(['permission:appartements.gerer'])->group(function () {
                Route::post('/lots', [LotController::class, 'store']);
                Route::post('/lots/bulk', [LotController::class, 'bulkStore']);
                Route::put('/lots/{lot}', [LotController::class, 'update']);
                Route::delete('/lots/{lot}', [LotController::class, 'destroy']);
                Route::post('/lots/{lot}/owners', [LotOwnerController::class, 'store']);

                Route::post('/lots/{lot}/references', [LotReferenceController::class, 'store']);
                Route::delete('/lot-references/{reference}', [LotReferenceController::class, 'destroy']);
            });

            Route::middleware(['permission:cotisations.modifier'])->group(function () {
                Route::post('/fund-calls/generate', [FundCallController::class, 'generate']);
                Route::post('/fund-calls', [FundCallController::class, 'store']);
                Route::put('/fund-calls/{fundCall}/opening-balance', [FundCallController::class, 'updateOpeningBalance']);
                Route::delete('/fund-calls/{fundCall}', [FundCallController::class, 'destroy']);

                Route::post('/fund-calls/{fundCall}/payments', [PaymentController::class, 'store']);
                Route::put('/fund-calls/{fundCall}/payments/{payment}', [PaymentController::class, 'update']);
                Route::delete('/fund-calls/{fundCall}/payments/{payment}', [PaymentController::class, 'destroy']);

                Route::post('/lots/{lot}/payments/bulk', [PaymentController::class, 'bulk']);
                Route::put('/payment-batches/{batchId}', [PaymentController::class, 'updateBatch']);
                Route::delete('/payment-batches/{batchId}', [PaymentController::class, 'destroyBatch']);
            });

            Route::middleware(['permission:depenses.modifier'])->group(function () {
                Route::post('/expenses', [ExpenseController::class, 'store']);
                Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
                Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);

                Route::post('/expense-categories', [ExpenseCategoryController::class, 'store']);
                Route::put('/expense-categories/reorder', [ExpenseCategoryController::class, 'reorder']);
                Route::put('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update']);
                Route::delete('/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy']);
            });

            Route::middleware(['permission:recettes.modifier'])->group(function () {
                Route::post('/revenues', [RevenueController::class, 'store']);
                Route::delete('/revenues/{revenue}', [RevenueController::class, 'destroy']);

                Route::post('/revenue-categories', [RevenueCategoryController::class, 'store']);
                Route::put('/revenue-categories/{revenueCategory}', [RevenueCategoryController::class, 'update']);
                Route::delete('/revenue-categories/{revenueCategory}', [RevenueCategoryController::class, 'destroy']);
            });
        });
    });

    // Platform back-office: cross-residence subscription management for the
    // Atlasoft Syndic team, entirely separate from the tenant app above.
    Route::middleware('platform.admin')->prefix('platform')->group(function () {
        Route::get('/residences', [PlatformSubscriptionsController::class, 'index']);
        Route::post('/residences/{residence}/activate', [PlatformSubscriptionsController::class, 'activate']);
        Route::post('/residences/{residence}/deactivate', [PlatformSubscriptionsController::class, 'deactivate']);
    });
});
