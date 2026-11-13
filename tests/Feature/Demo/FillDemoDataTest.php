<?php

use App\Enums\PlanStatus;
use App\Enums\SaleType;
use App\Models\CashSweep;
use App\Models\Expense;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockTransfer;
use App\Models\StockUnit;
use App\Models\StockUnitPhoto;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Support\StoreFixture;

beforeEach(function () {
    Storage::fake('public');
    // Sana qat'iy: natija test qaysi kuni ishga tushishiga bog'liq bo'lmasin.
    $this->travelTo('2026-11-14 12:00:00');
    $this->store = StoreFixture::create();
    app(TenantContext::class)->forget();
});

it('fills a store with a month of consistent activity across every process', function () {
    $this->artisan('demo:fill', ['tenant' => $this->store->tenant->id])->assertSuccessful();

    // Buyruq vaqtni surib ishlaydi — tugagach hozirgi vaqt tiklanadi.
    expect(Carbon::hasTestNow())->toBeFalse();

    app(TenantContext::class)->runFor($this->store->tenant, function () {
        expect(Sale::query()->count())->toBeGreaterThan(60)
            ->and(Sale::query()->where('type', SaleType::Installment)->count())->toBeGreaterThan(5)
            ->and(InstallmentPayment::query()->where('is_initial', false)->count())->toBeGreaterThan(0)
            ->and(InstallmentPlan::query()->where('status', PlanStatus::Active)->count())->toBeGreaterThan(0)
            ->and(Expense::query()->count())->toBeGreaterThan(6)
            ->and(CashSweep::query()->count())->toBeGreaterThan(1)
            ->and(StockTransfer::query()->count())->toBeGreaterThan(1)
            ->and(SaleReturn::query()->count())->toBeGreaterThan(0)
            ->and(StockUnit::query()->count())->toBeGreaterThan(30);

        // Har bir telefonning o'z rasmlari va mahsulotlarning model rasmi.
        expect(StockUnitPhoto::query()->count())->toBe(StockUnit::query()->count() * 2)
            ->and(Product::query()->whereNull('image_path')->count())->toBe(0);

        // Savdolar butun oyga yoyilgan: birinchi va oxirgi savdo orasida kamida 25 kun.
        $first = Sale::query()->min('created_at');
        $last = Sale::query()->max('created_at');
        expect(Carbon::parse($first)->diffInDays(Carbon::parse($last)))->toBeGreaterThan(25);
    });

    app(TenantContext::class)->forget();

    // Kassa, nasiya va ombor yozuvlari o'zaro mos kelishi shart.
    $this->artisan('ledger:check')->assertSuccessful();
});

it('refuses to add on top of a busy store unless forced', function () {
    $this->artisan('demo:fill', ['tenant' => $this->store->tenant->id])->assertSuccessful();
    $this->artisan('demo:fill', ['tenant' => $this->store->tenant->id])->assertFailed();
    $this->artisan('demo:fill', ['tenant' => 999999])->assertFailed();
});
