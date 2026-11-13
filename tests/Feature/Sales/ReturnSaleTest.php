<?php

namespace Tests\Feature\Sales;

use App\Actions\Sales\CloseSale;
use App\Actions\Sales\ReturnSale;
use App\Enums\CashAccountType;
use App\Enums\Currency;
use App\Enums\SaleStatus;
use App\Enums\StockStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\StockBatch;
use App\Services\Cash\CashService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class ReturnSaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_return_brings_cash_stock_and_profit_back(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1);
        $sale = app(CloseSale::class)->handle($store->branch, $store->owner, [['stock_unit_id' => $phone->id]], [
            ['method' => 'cash', 'currency' => 'UZS', 'amount' => '4850000'],
        ]);
        $item = $sale->items()->firstOrFail();

        $return = app(ReturnSale::class)->handle($sale, $store->owner, [['sale_item_id' => $item->id, 'qty' => 1]], CashAccountType::Cash, 'Mijoz fikridan qaytdi');

        $this->assertSame('4850000.00', $return->refund_amount);
        $this->assertSame($item->profit, $return->profit_reversed);
        $this->assertSame(SaleStatus::Returned, $sale->fresh()->status);
        $this->assertSame(StockStatus::Returned, $phone->fresh()->status);
        $this->assertTrue(app(CashService::class)->balance($store->cashAccount(), Currency::UZS)->isZero());
    }

    public function test_partial_return_puts_accessories_back_into_their_batches(): void
    {
        $store = StoreFixture::create();
        $store->receiveCases(10, '20000');
        $case = $store->receiveCases(10, '25000');
        $sale = app(CloseSale::class)->handle($store->branch, $store->owner, [['variant_id' => $case->id, 'qty' => 12]], [
            ['method' => 'cash', 'currency' => 'UZS', 'amount' => '720000'],
        ]);
        $item = $sale->items()->firstOrFail();

        $return = app(ReturnSale::class)->handle($sale, $store->owner, [['sale_item_id' => $item->id, 'qty' => 5]], CashAccountType::Cash);

        // 5 × 60 000 qaytadi; foydaning 5/12 qismi teskari yoziladi.
        $this->assertSame('300000.00', $return->refund_amount);
        $this->assertSame('195833.33', $return->profit_reversed);
        $this->assertSame(SaleStatus::PartiallyReturned, $sale->fresh()->status);
        $this->assertSame(5, $item->fresh()->returned_qty);

        // Oxirgi olingan partiyaga avval qaytadi: 25 000 lik partiya to'ladi (10), 20 000 likka 3 ta.
        $this->assertSame([3, 10], StockBatch::query()->orderBy('id')->pluck('qty_left')->all());
        $this->assertSame('420000.00', app(CashService::class)->balance($store->cashAccount(), Currency::UZS)->amount);
    }

    public function test_returning_the_rest_leaves_no_rounding_remainder(): void
    {
        $store = StoreFixture::create();
        $case = $store->receiveCases(3, '20000', '100000');
        $sale = app(CloseSale::class)->handle($store->branch, $store->owner, [['variant_id' => $case->id, 'qty' => 3, 'discount' => '0']], [
            ['method' => 'cash', 'currency' => 'UZS', 'amount' => '300000'],
        ]);
        $item = $sale->items()->firstOrFail();
        $action = app(ReturnSale::class);

        $first = $action->handle($sale, $store->owner, [['sale_item_id' => $item->id, 'qty' => 1]], CashAccountType::Cash);
        $second = $action->handle($sale, $store->owner, [['sale_item_id' => $item->id, 'qty' => 2]], CashAccountType::Cash);

        $refunded = Money::uzs($first->refund_amount)->plus(Money::uzs($second->refund_amount));
        $reversed = Money::uzs($first->profit_reversed)->plus(Money::uzs($second->profit_reversed));

        $this->assertSame('300000.00', $refunded->amount);
        $this->assertSame($item->profit, $reversed->amount);
        $this->assertSame(SaleStatus::Returned, $sale->fresh()->status);
    }

    public function test_more_than_was_sold_cannot_be_returned(): void
    {
        $store = StoreFixture::create();
        $case = $store->receiveCases(5);
        $sale = app(CloseSale::class)->handle($store->branch, $store->owner, [['variant_id' => $case->id, 'qty' => 2]], [
            ['method' => 'cash', 'currency' => 'UZS', 'amount' => '120000'],
        ]);
        $item = $sale->items()->firstOrFail();

        $this->expectException(BusinessRuleException::class);

        app(ReturnSale::class)->handle($sale, $store->owner, [['sale_item_id' => $item->id, 'qty' => 3]], CashAccountType::Cash);
    }

    public function test_return_is_rejected_when_the_cash_account_is_short(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1);
        $sale = app(CloseSale::class)->handle($store->branch, $store->owner, [['stock_unit_id' => $phone->id]], [
            ['method' => 'card', 'currency' => 'UZS', 'amount' => '4850000'],
        ]);
        $item = $sale->items()->firstOrFail();

        // Pul kartaga tushgan, naqd kassada yo'q.
        try {
            app(ReturnSale::class)->handle($sale, $store->owner, [['sale_item_id' => $item->id, 'qty' => 1]], CashAccountType::Cash);
            $this->fail('Qaytarish rad etilishi kerak edi.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('CASH_INSUFFICIENT', $exception->errorCode);
        }

        $this->assertSame(StockStatus::Sold, $phone->fresh()->status);
        $this->assertSame(SaleStatus::Completed, $sale->fresh()->status);
    }
}
