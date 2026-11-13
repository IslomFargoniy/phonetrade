<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InstallmentsController;
use App\Http\Controllers\Api\OwnerController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Stock\UnitPhotoController;
use App\Http\Controllers\Telegram\WebhookController;
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
    Route::post('demo-login', [AuthController::class, 'demo'])->middleware('throttle:10,1')->name('demo-login');

    Route::middleware(['auth:sanctum', 'role:owner,seller', 'throttle:240,1'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::delete('account', [AccountController::class, 'destroyViaApi'])->name('account.destroy');

        Route::get('stock/lookup', [SalesController::class, 'lookup'])->name('stock.lookup');
        Route::get('stock', [SalesController::class, 'stock'])->name('stock.index');
        Route::post('stock/units/{unit}/photos', [UnitPhotoController::class, 'storeViaApi'])->name('stock.units.photos.store');
        Route::delete('stock/photos/{photo}', [UnitPhotoController::class, 'destroyViaApi'])->name('stock.photos.destroy');
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

// Telegram bot yangilanishlari. Maxfiy sarlavha bilan tekshiriladi (WebhookController).
Route::post('telegram/webhook', WebhookController::class)->middleware('throttle:300,1')->name('telegram.webhook');
