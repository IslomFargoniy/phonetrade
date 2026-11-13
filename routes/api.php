<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InstallmentsController;
use App\Http\Controllers\Api\OwnerController;
use App\Http\Controllers\Api\SalesController;
use Illuminate\Support\Facades\Route;

/*
 * Mobil ilovalar uchun API. Hisob-kitob web bilan bitta kod orqali bajariladi.
 *
 * - Autentifikatsiya: `Authorization: Bearer <token>` (Sanctum).
 * - Filial: `X-Branch-Id` sarlavhasi; berilmasa birinchi mavjud filial.
 * - Pul yozadigan so'rovlar: `Idempotency-Key` sarlavhasi majburiy.
 */
Route::prefix('v1')->name('api.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

    Route::middleware(['auth:sanctum', 'role:owner,seller', 'throttle:240,1'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');

        Route::get('stock/lookup', [SalesController::class, 'lookup'])->name('stock.lookup');
        Route::get('stock', [SalesController::class, 'stock'])->name('stock.index');
        Route::get('customers/lookup', [SalesController::class, 'customer'])->name('customers.lookup');
        Route::get('customers', [SalesController::class, 'customers'])->name('customers.index');

        Route::post('sales/preview', [SalesController::class, 'preview'])->name('sales.preview');
        Route::post('sales', [SalesController::class, 'store'])->middleware('idempotent')->name('sales.store');
        Route::get('sales/{sale}', [SalesController::class, 'show'])->name('sales.show');

        Route::get('installments', [InstallmentsController::class, 'index'])->name('installments.index');
        Route::get('installments/{plan}', [InstallmentsController::class, 'show'])->name('installments.show');
        Route::post('installments/{plan}/payments', [InstallmentsController::class, 'pay'])->middleware('idempotent')->name('installments.pay');

        Route::middleware('role:owner')->group(function () {
            Route::get('reports/summary', [OwnerController::class, 'summary'])->name('reports.summary');
            Route::get('cash', [OwnerController::class, 'cash'])->name('cash.index');
            Route::post('cash/sweep', [OwnerController::class, 'sweep'])->middleware('idempotent')->name('cash.sweep');
        });
    });
});
