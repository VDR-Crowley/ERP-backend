<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\RefreshTokenController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\BusinessLineReportController;
use App\Http\Controllers\Api\CashFlowController;
use App\Http\Controllers\Api\DailyProductionController;
use App\Http\Controllers\Api\EggStockController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FeedOpenLogController;
use App\Http\Controllers\Api\FeedStockController;
use App\Http\Controllers\Api\FlockCleaningController;
use App\Http\Controllers\Api\FlockController;
use App\Http\Controllers\Api\FlockIncubationController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VendedorController;
use App\Http\Controllers\Api\VendorStockController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

Route::post('/password/forgot', [PasswordResetController::class, 'requestCode'])->middleware('throttle:password-reset');
Route::post('/password/verify-code', [PasswordResetController::class, 'verifyCode'])->middleware('throttle:password-reset');
Route::post('/password/reset', [PasswordResetController::class, 'reset'])->middleware('throttle:password-reset');

// Só aceita token com ability `refresh` — rotaciona o par (access+refresh).
Route::post('/refresh', [RefreshTokenController::class, 'store'])
    ->middleware(['auth:sanctum', 'abilities:refresh']);

// Rotas normais da API: exigem token com ability `access` (um refresh token
// vazando não serve pra nada além de chamar /refresh).
Route::middleware(['auth:sanctum', 'abilities:access'])->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::post('/logout', [LogoutController::class, 'store']);

    // Entidades core (ver docs/plano-entidades.md) — CRUD básico pronto;
    // ações especiais registradas como esqueleto (stub), lógica de negócio
    // fica pra próxima etapa.
    Route::apiResource('products', ProductController::class);
    Route::apiResource('vendedores', VendedorController::class)->parameters(['vendedores' => 'vendedor']);
    Route::apiResource('flock', FlockController::class);

    Route::apiResource('flock-incubations', FlockIncubationController::class);
    Route::get('flock-incubations/{flock_incubation}/hatch-events', [FlockIncubationController::class, 'hatchEvents']);
    Route::post('flock-incubations/{flock_incubation}/hatch-events', [FlockIncubationController::class, 'storeHatchEvent']);
    Route::put('flock-incubations/{flock_incubation}/hatch-events/{hatch_event}', [FlockIncubationController::class, 'updateHatchEvent']);
    Route::delete('flock-incubations/{flock_incubation}/hatch-events/{hatch_event}', [FlockIncubationController::class, 'destroyHatchEvent']);

    Route::apiResource('vendor-stock', VendorStockController::class);

    Route::apiResource('sales', SaleController::class);
    Route::post('sales/{sale}/exclusion', [SaleController::class, 'storeExclusion']);
    Route::delete('sales/{sale}/exclusion', [SaleController::class, 'destroyExclusion']);

    Route::apiResource('stock-transfers', StockTransferController::class);
    Route::apiResource('daily-productions', DailyProductionController::class);
    Route::apiResource('egg-stocks', EggStockController::class);

    Route::apiResource('expenses', ExpenseController::class);
    Route::post('expenses/{expense}/species-override', [ExpenseController::class, 'storeSpeciesOverride']);
    Route::delete('expenses/{expense}/species-override', [ExpenseController::class, 'destroySpeciesOverride']);

    Route::apiResource('cash-flows', CashFlowController::class);

    Route::apiResource('feed-stocks', FeedStockController::class);
    Route::post('feed-stocks/{feed_stock}/replenish', [FeedStockController::class, 'replenish']);
    Route::post('feed-stocks/{feed_stock}/open-bag', [FeedStockController::class, 'openBag']);

    Route::get('feed-open-logs', [FeedOpenLogController::class, 'index']);

    Route::apiResource('flock-cleanings', FlockCleaningController::class);

    Route::get('business-line-report', [BusinessLineReportController::class, 'show']);
});
