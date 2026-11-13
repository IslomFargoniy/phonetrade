<?php

use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Cash\CashController;
use App\Http\Controllers\Customers\CustomerController;
use App\Http\Controllers\Installments\InstallmentController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Sales\PosController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\Stock\CatalogController;
use App\Http\Controllers\Stock\HistoryController;
use App\Http\Controllers\Stock\ReceiptController;
use App\Http\Controllers\Stock\StockController;
use App\Http\Controllers\Stock\TransferController;
use App\Http\Controllers\Store\BranchController;
use App\Http\Controllers\Store\StaffController;
use App\Http\Controllers\Store\StoreSettingsController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth'])->group(function () {
    // Rolga qarab boshlang'ich sahifa: superadmin do'konlar ro'yxatiga o'tadi.
    Route::get('dashboard', function (Request $request) {
        /** @var User $user */
        $user = $request->user();

        // Har rol o'zining asosiy ish joyiga tushadi.
        return match (true) {
            $user->isSuperadmin() => to_route('admin.tenants.index'),
            $user->isOwner() => to_route('reports.index'),
            default => to_route('pos.index'),
        };
    })->name('dashboard');

    Route::post('branch/{branch}/switch', [BranchController::class, 'select'])
        ->middleware('role:owner,seller')
        ->name('branches.select');
});

/*
 * Ombor: do'kon egasi va sotuvchi. Tannarx sotuvchiga berilmaydi (StockPresenter).
 */
Route::middleware(['auth', 'role:owner,seller'])->group(function () {
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('pos/lookup', [PosController::class, 'lookup'])->name('pos.lookup');
    Route::post('pos/preview', [PosController::class, 'preview'])->name('pos.preview');

    Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
    Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
    Route::post('sales/{sale}/refund', [SaleController::class, 'refund'])->name('sales.refund');

    Route::get('installments', [InstallmentController::class, 'index'])->name('installments.index');
    Route::post('installments/{plan}/pay', [InstallmentController::class, 'pay'])->name('installments.pay');

    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('customers/lookup', [CustomerController::class, 'lookup'])->name('customers.lookup');
    Route::post('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('customers/files/{file}', [CustomerController::class, 'file'])->name('customers.files.show');

    Route::get('cash', [CashController::class, 'index'])->name('cash.index');
    Route::middleware('role:owner')->group(function () {
        Route::post('cash/categories', [CashController::class, 'storeCategory'])->name('cash.categories.store');
        Route::post('cash/expenses', [CashController::class, 'storeExpense'])->name('cash.expenses.store');
        Route::post('cash/sweep', [CashController::class, 'sweep'])->name('cash.sweep');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    Route::get('stock/history', HistoryController::class)->name('stock.history');
    Route::get('stock', [StockController::class, 'index'])->name('stock.index');
    Route::post('stock/units/{unit}/restock', [StockController::class, 'restock'])->middleware('role:owner')->name('stock.units.restock');
    Route::patch('stock/units/{unit}', [StockController::class, 'updateUnit'])->middleware('role:owner')->name('stock.units.update');

    Route::get('receipts/create', [ReceiptController::class, 'create'])->name('receipts.create');
    Route::post('receipts', [ReceiptController::class, 'store'])->name('receipts.store');
    Route::get('receipts/{purchase}', [ReceiptController::class, 'show'])->name('receipts.show');

    Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
    Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
    Route::post('transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('transfers.receive');
});

/*
 * Superadmin paneli. Bu yerda do'kon konteksti o'rnatilmaydi, shuning uchun
 * so'rovlar barcha do'konlarni ko'radi.
 */
Route::middleware(['auth', 'role:superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::post('tenants', [TenantController::class, 'store'])->name('tenants.store');
    Route::patch('tenants/{tenant}/status', [TenantController::class, 'updateStatus'])->name('tenants.status');
    Route::get('tenants/{tenant}', [BillingController::class, 'show'])->name('tenants.show');
    Route::put('tenants/{tenant}/subscription', [BillingController::class, 'update'])->name('tenants.subscription');
    Route::post('tenants/{tenant}/payments', [BillingController::class, 'storePayment'])->name('tenants.payments');
    Route::get('plans', [PlanController::class, 'index'])->name('plans.index');
    Route::post('plans', [PlanController::class, 'store'])->name('plans.store');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
});

/*
 * Do'kon sozlamalari — faqat egasi uchun.
 */
Route::middleware(['auth', 'role:owner'])->prefix('settings')->group(function () {
    Route::get('store', [StoreSettingsController::class, 'edit'])->name('store-settings.edit');
    Route::put('store/rate', [StoreSettingsController::class, 'updateRate'])->name('store-settings.rate');
    Route::put('store/general', [StoreSettingsController::class, 'updateGeneral'])->name('store-settings.general');
    Route::delete('store/telegram', [StoreSettingsController::class, 'unlinkTelegram'])->name('store-settings.telegram.unlink');
    Route::put('store/installment-rates/{branch}', [StoreSettingsController::class, 'updateInstallmentRates'])
        ->name('store-settings.installment-rates');

    Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
    Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
    Route::patch('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');

    Route::get('catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::post('catalog/categories', [CatalogController::class, 'storeCategory'])->name('catalog.categories.store');
    Route::post('catalog/attributes', [CatalogController::class, 'storeAttribute'])->name('catalog.attributes.store');
    Route::patch('catalog/attributes/{attribute}', [CatalogController::class, 'updateAttribute'])->name('catalog.attributes.update');
    Route::post('catalog/products', [CatalogController::class, 'storeProduct'])->name('catalog.products.store');
    Route::patch('catalog/products/{product}', [CatalogController::class, 'updateProduct'])->name('catalog.products.update');
    Route::post('catalog/suppliers', [CatalogController::class, 'storeSupplier'])->name('catalog.suppliers.store');

    Route::get('staff', [StaffController::class, 'index'])->name('staff.index');
    Route::post('staff', [StaffController::class, 'store'])->name('staff.store');
    Route::patch('staff/{staff}', [StaffController::class, 'update'])->name('staff.update');
});

require __DIR__.'/settings.php';
