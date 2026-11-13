<?php

namespace Tests\Feature\Stock;

use App\Enums\Currency;
use App\Exceptions\BusinessRuleException;
use App\Models\CashMovement;
use App\Services\Cash\CashService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class CashServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_is_the_sum_of_movements_per_currency(): void
    {
        $store = StoreFixture::create();
        $cash = app(CashService::class);
        $account = $store->cashAccount();

        $cash->deposit($account, Money::uzs(4000000), 'sale');
        $cash->deposit($account, Money::usd(400), 'sale');
        $cash->withdraw($account, Money::uzs(500000), 'expense');

        $this->assertSame('3500000.00', $cash->balance($account, Currency::UZS)->amount);
        $this->assertSame('400.00', $cash->balance($account, Currency::USD)->amount);
    }

    public function test_withdrawal_above_the_balance_is_rejected(): void
    {
        $store = StoreFixture::create();
        $cash = app(CashService::class);
        $account = $store->cashAccount();

        $cash->deposit($account, Money::uzs(400000), 'sale');

        try {
            $cash->withdraw($account, Money::uzs(500000), 'trade_in');
            $this->fail('Chiqim rad etilishi kerak edi.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('CASH_INSUFFICIENT', $exception->errorCode);
        }

        $this->assertSame(1, CashMovement::query()->count());
        $this->assertSame('400000.00', $cash->balance($account, Currency::UZS)->amount);
    }

    public function test_dollars_cannot_be_paid_out_of_the_sum_balance(): void
    {
        $store = StoreFixture::create();
        $cash = app(CashService::class);
        $account = $store->cashAccount();

        $cash->deposit($account, Money::uzs(50000000), 'sale');

        $this->expectException(BusinessRuleException::class);

        $cash->withdraw($account, Money::usd(100), 'trade_in');
    }

    public function test_whole_balance_can_be_withdrawn(): void
    {
        $store = StoreFixture::create();
        $cash = app(CashService::class);
        $account = $store->cashAccount();

        $cash->deposit($account, Money::uzs(500000), 'sale');
        $cash->withdraw($account, Money::uzs(500000), 'trade_in');

        $this->assertTrue($cash->balance($account, Currency::UZS)->isZero());
    }

    public function test_zero_or_negative_amounts_are_rejected(): void
    {
        $store = StoreFixture::create();

        $this->expectException(BusinessRuleException::class);

        app(CashService::class)->deposit($store->cashAccount(), Money::uzs(0), 'sale');
    }
}
