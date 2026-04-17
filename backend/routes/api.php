<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CameraController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\DispatchController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Internal routes (for docker network services)
Route::get('/internal/cameras/{id}/stream-info', [\App\Http\Controllers\Api\InternalCameraController::class, 'getStreamInfo']);

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Internal Worker routes (Secured via Secret Header loosely by Controller)
Route::post('/vouchers/check-eligibility', [\App\Http\Controllers\Api\VoucherController::class, 'checkEligibility']);
Route::post('/vouchers/issue', [\App\Http\Controllers\Api\VoucherController::class, 'issue']);
Route::post('/internal/trends/sync', [\App\Http\Controllers\Api\TrendSuggestionController::class, 'sync']);

// Payment Webhooks & Testing
Route::post('/webhooks/payment', [WebhookController::class, 'handlePayOS']);
Route::get('/test-payment/{id}', [WebhookController::class, 'testSuccess']);
Route::post('/test-payment/simulate', [WebhookController::class, 'simulatePayment']);


// Authenticated & Tenant Scoped routes
Route::middleware(['auth:sanctum', \App\Http\Middleware\TenantMiddleware::class])->group(function () {
    
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    // Shifts
    Route::prefix('shifts')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ShiftController::class, 'index']);
        Route::get('/current', [\App\Http\Controllers\Api\ShiftController::class, 'current']);
        Route::post('/start', [\App\Http\Controllers\Api\ShiftController::class, 'start']);
        Route::post('/end', [\App\Http\Controllers\Api\ShiftController::class, 'end']);
    });

    // Users and Roles Management
    Route::middleware('role:admin,manager')->group(function () {
        Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
        Route::put('/users/{user}/assign-role', [\App\Http\Controllers\Api\UserController::class, 'assignRole']);
        Route::get('/roles', [\App\Http\Controllers\Api\RoleController::class, 'index']);
        Route::get('/roles/permissions', [\App\Http\Controllers\Api\RoleController::class, 'permissions']);
        Route::post('/roles/{role}/sync-permissions', [\App\Http\Controllers\Api\RoleController::class, 'syncPermissions']);
    });
    
    // Cameras CRUD
    Route::apiResource('cameras', CameraController::class);
    
    // Categories (Reading: Everyone, Writing: Admin/Manager)
    Route::get('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
    Route::middleware('role:admin,manager')->group(function () {
        Route::apiResource('categories', \App\Http\Controllers\Api\CategoryController::class)->except(['index']);
    });
    
    // Products (Reading: Everyone, Writing: Admin/Manager)
    Route::get('/products', [\App\Http\Controllers\Api\ProductController::class, 'index']);
    Route::get('/products/{product}', [\App\Http\Controllers\Api\ProductController::class, 'show']);
    
    // Inventory and Suppliers (Admin/Manager only)
    Route::middleware('role:admin,manager')->group(function () {
        Route::post('/products', [\App\Http\Controllers\Api\ProductController::class, 'store']);
        Route::put('/products/{product}', [\App\Http\Controllers\Api\ProductController::class, 'update']);
        Route::delete('/products/{product}', [\App\Http\Controllers\Api\ProductController::class, 'destroy']);

        // Suppliers
        Route::apiResource('suppliers', \App\Http\Controllers\Api\SupplierController::class);

        // Materials
        Route::apiResource('materials', \App\Http\Controllers\Api\MaterialController::class);

        // Inventory
        Route::prefix('inventory')->group(function () {
            Route::get('/history', [\App\Http\Controllers\Api\InventoryController::class, 'history']);
            Route::get('/low-stock', [\App\Http\Controllers\Api\InventoryController::class, 'lowStock']);
            Route::post('/import', [\App\Http\Controllers\Api\InventoryController::class, 'import']);
            Route::post('/adjust', [\App\Http\Controllers\Api\InventoryController::class, 'adjust']);
            Route::post('/waste', [\App\Http\Controllers\Api\InventoryController::class, 'waste']);
            Route::apiResource('imports', \App\Http\Controllers\Api\StockImportController::class)->only(['index', 'store', 'show']);
        });
    });
    
    // Dashboards
    Route::get('/dashboard/stats', [\App\Http\Controllers\Api\DashboardController::class, 'stats']);

    // Customers & Loyalty
    Route::get('/customers/search', [\App\Http\Controllers\Api\CustomerController::class, 'search']);
    Route::get('/customers/{id}/vouchers', [\App\Http\Controllers\Api\VoucherController::class, 'getForCustomer']);
    Route::apiResource('customers', \App\Http\Controllers\Api\CustomerController::class);
    Route::apiResource('customer-groups', \App\Http\Controllers\Api\CustomerGroupController::class);

    // Orders & Dispatch
    Route::get('/orders/stats', [OrderController::class, 'stats']);
    Route::post('/orders/{id}/payos-link', [OrderController::class, 'generatePaymentLink']);
    Route::get('/orders/{id}/check-payment', [OrderController::class, 'checkPaymentStatus']);
    Route::apiResource('orders', OrderController::class)->middleware('check.shift');
    
    Route::get('/dispatch', [DispatchController::class, 'index']);
    Route::put('/dispatch/{id}/status', [DispatchController::class, 'updateStatus']);
    
    // Reports
    Route::middleware('role:admin,manager')->prefix('reports')->group(function () {
        Route::get('/stats', [\App\Http\Controllers\Api\ReportController::class, 'stats']);
        Route::get('/top-profitable', [\App\Http\Controllers\Api\ReportController::class, 'topProfitableProducts']);
        Route::get('/hourly-density', [\App\Http\Controllers\Api\ReportController::class, 'hourlySalesDensity']);
        Route::get('/staff-performance', [\App\Http\Controllers\Api\ReportController::class, 'staffPerformance']);
        Route::get('/staff-performance/{user}/history', [\App\Http\Controllers\Api\ReportController::class, 'staffOrderHistory']);
        Route::get('/export/excel', [\App\Http\Controllers\Api\ReportController::class, 'exportExcel']);
        Route::get('/print/{order}', [\App\Http\Controllers\Api\ReportController::class, 'reprintOrder']);

        // Trend Suggestions
        Route::get('/trends', [\App\Http\Controllers\Api\TrendSuggestionController::class, 'index']);
        Route::post('/trends/{id}/add-to-menu', [\App\Http\Controllers\Api\TrendSuggestionController::class, 'addToMenu']);
    });

});
