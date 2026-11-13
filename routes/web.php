<?php

use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Store\BranchController;
use App\Http\Controllers\Store\StaffController;
use App\Http\Controllers\Store\StoreSettingsController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth'])->group(function () {
    // Rolga qarab boshlang'ich sahifa: superadmin do'konlar ro'yxatiga o'tadi.
    Route::get('dashboard', function (Request $request) {
        /** @var User $user */
        $user = $request->user();

        return $user->isSuperadmin()
            ? to_route('admin.tenants.index')
            : Inertia::render('dashboard');
    })->name('dashboard');

    Route::post('branch/{branch}/switch', [BranchController::class, 'select'])
        ->middleware('role:owner,seller')
        ->name('branches.select');
});

/*
 * Superadmin paneli. Bu yerda do'kon konteksti o'rnatilmaydi, shuning uchun
 * so'rovlar barcha do'konlarni ko'radi.
 */
Route::middleware(['auth', 'role:superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::patch('tenants/{tenant}/status', [TenantController::class, 'updateStatus'])->name('tenants.status');
});

/*
 * Do'kon sozlamalari — faqat egasi uchun.
 */
Route::middleware(['auth', 'role:owner'])->prefix('settings')->group(function () {
    Route::get('store', [StoreSettingsController::class, 'edit'])->name('store-settings.edit');
    Route::put('store/rate', [StoreSettingsController::class, 'updateRate'])->name('store-settings.rate');
    Route::put('store/general', [StoreSettingsController::class, 'updateGeneral'])->name('store-settings.general');
    Route::put('store/installment-rates/{branch}', [StoreSettingsController::class, 'updateInstallmentRates'])
        ->name('store-settings.installment-rates');

    Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
    Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
    Route::patch('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');

    Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
    Route::patch('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
});

require __DIR__.'/settings.php';
