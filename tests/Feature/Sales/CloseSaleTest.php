<?php

namespace Tests\Feature\Sales;

use App\Actions\Sales\CloseSale;
use App\Enums\CashAccountType;
use App\Enums\Currency;
use App\Enums\StockStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Sale;
use App\Services\Cash\CashService;
use App\Services\Stock\BatchConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class CloseSaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array{method: string, currency: string, amount: string}>  $payments
     */
    private function sell(StoreFixture $store, array $items, array $payments): Sale
    {
        return app(CloseSale::class)->handle($store->branch, $store->owner, $items, $payments);
    }

    public function test_mixed_currency_cart_is_totalled_in_sum_and_paid_in_both_currencies(): void
    {
        // Texnik rejadagi misol: $460 × 12 750 + 60 000 = 5 925 000 so'm
        $store = StoreFixture::create();
        $iphone = $store->receivePhone(1, ['sale_price' => '460', 'sale_currency' => 'USD', 'unit_cost' => '400']);
        $case = $store->receiveCases(18);

        $sale = $this->sell($store, [
            ['stock_unit_id' => $iphone->id],
            ['variant_id' => $case->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'currency' => 'USD', 'amount' => '400'],
            ['method' => 'card', 'currency' => 'UZS', 'amount' => '825000'],
        ]);

        $this->assertSame(Currency::UZS, $sale->currency);
        $this->assertSame('5925000.00', $sale->total);
        $this->assertSame('0.00', $sale->change);
        $this->assertSame('12750.00', $sale->rate);

        // Har to'lov o'z valyutasidagi balansga tushadi.
        $cash = app(CashService::class);
        $this->assertSame('400.00', $cash->balance($store->cashAccount(CashAccountType::Cash), Currency::USD)->amount);
        $this->assertTrue($cash->balance($store->cashAccount(CashAccountType::Cash), Currency::UZS)->isZero());
        $this->assertSame('825000.00', $cash->balance($store->cashAccount(CashAccountType::Card), Currency::UZS)->amount);

        $this->assertSame(StockStatus::Sold, $iphone->fresh()->status);
        $this->assertSame(17, app(BatchConsumer::class)->available($case, $store->branch));
    }

    public function test_profit_uses_the_purchase_day_rate_not_todays(): void
    {
        $store = StoreFixture::create('12750');
        $phone = $store->receivePhone(1);
        $store->setRate('13000');

        $sale = $this->sell($store, [['stock_unit_id' => $phone->id, 'discount' => '150000']], [
            ['method' => 'cash', 'currency' => 'UZS', 'amount' => '4700000'],
        ]);

        $item = $sale->items()->firstOrFail();

        // Tannarx $320 × 12 750 (kirim kursi) = 4 080 000; foyda 4 700 000 − 4 080 000.
        $this->assertSame('4080000.00', $item->cost_snapshot);
        $this->assertSame('620000.00', $item->profit);
        $this->assertSame('150000.00', $sale->discount);
        $this->assertSame(now()->addMonthsNoOverflow(12)->toDateString(), $item->warranty_until->toDateString());
    }

    public function test_accessory_cost_is_taken_first_in_first_out(): void
    {
        $store = StoreFixture::create();
        $store->receiveCases(10, '20000');
        $case = $store->receiveCases(10, '25000');

        $sale = $this->sell($store, [['variant_id' => $case->id, 'qty' => 12]], [
            ['method' => 'cash', 'currency' => 'UZS', 'amount' => '720000'],
        ]);

        $item = $sale->items()->firstOrFail();

        // 10 × 20 000 + 2 × 25 000 = 250 000
        $this->assertSame('250000.00', $item->cost_snapshot);
        $this->assertSame('470000.00', $item->profit);
        $this->assertSame([10, 2], $item->batches()->orderBy('id')->pluck('qty')->all());
    }

    public function test_discount_cannot_exceed_the_limit_set_at_receipt(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1);
        $pay = [['method' => 'cash', 'currency' => 'UZS', 'amount' => '4850000']];

        // 15% × 4 850 000 = 727 500
        try {
            $this->sell($store, [['stock_unit_id' => $phone->id, 'discount' => '727501']], $pay);
            $this->fail('Chegirma rad etilishi kerak edi.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('DISCOUNT_LIMIT_EXCEEDED', $exception->errorCode);
        }

        $this->assertSame(StockStatus::InStock, $phone->fresh()->status);

        $sale = $this->sell($store, [['stock_unit_id' => $phone->id, 'discount' => '727500']], $pay);

        $this->assertSame('4122500.00', $sale->total);
    }

    public function test_the_same_phone_cannot_be_sold_twice(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1);
        $pay = [['method' => 'cash', 'currency' => 'UZS', 'amount' => '4850000']];

        $this->sell($store, [['stock_unit_id' => $phone->id]], $pay);

        try {
            $this->sell($store, [['stock_unit_id' => $phone->id]], $pay);
            $this->fail('Ikkinchi savdo rad etilishi kerak edi.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('STOCK_ALREADY_SOLD', $exception->errorCode);
        }

        $this->assertSame(1, Sale::query()->count());
    }

    public function test_underpaid_sale_saves_nothing(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1);

        try {
            $this->sell($store, [['stock_unit_id' => $phone->id]], [
                ['method' => 'cash', 'currency' => 'UZS', 'amount' => '4849999'],
            ]);
            $this->fail('Savdo rad etilishi kerak edi.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('PAYMENT_NOT_BALANCED', $exception->errorCode);
        }

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(StockStatus::InStock, $phone->fresh()->status);
        $this->assertTrue(app(CashService::class)->balance($store->cashAccount(), Currency::UZS)->isZero());
    }

    public function test_overpayment_is_given_back_as_change_in_the_sale_currency(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1);

        $sale = $this->sell($store, [['stock_unit_id' => $phone->id]], [
            ['method' => 'cash', 'currency' => 'UZS', 'amount' => '5000000'],
        ]);

        $this->assertSame('150000.00', $sale->change);
        $this->assertSame('4850000.00', app(CashService::class)->balance($store->cashAccount(), Currency::UZS)->amount);
    }

    public function test_dollar_only_cart_stays_in_dollars(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1, ['sale_price' => '460', 'sale_currency' => 'USD', 'unit_cost' => '400']);

        $sale = $this->sell($store, [['stock_unit_id' => $phone->id]], [
            ['method' => 'cash', 'currency' => 'USD', 'amount' => '460'],
        ]);

        $this->assertSame(Currency::USD, $sale->currency);
        $this->assertSame('460.00', $sale->total);
        $this->assertSame('60.00', $sale->items()->firstOrFail()->profit);
    }

    public function test_sale_numbers_are_sequential_within_the_store(): void
    {
        $store = StoreFixture::create();
        $pay = [['method' => 'cash', 'currency' => 'UZS', 'amount' => '4850000']];

        $first = $this->sell($store, [['stock_unit_id' => $store->receivePhone(1)->id]], $pay);
        $second = $this->sell($store, [['stock_unit_id' => $store->receivePhone(2)->id]], $pay);

        $this->assertSame([1, 2], [$first->number, $second->number]);
    }

    public function test_more_accessories_than_in_stock_cannot_be_sold(): void
    {
        $store = StoreFixture::create();
        $case = $store->receiveCases(3);

        try {
            $this->sell($store, [['variant_id' => $case->id, 'qty' => 4]], [
                ['method' => 'cash', 'currency' => 'UZS', 'amount' => '240000'],
            ]);
            $this->fail('Savdo rad etilishi kerak edi.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('STOCK_INSUFFICIENT', $exception->errorCode);
        }

        $this->assertSame(3, app(BatchConsumer::class)->available($case, $store->branch));
    }
}
