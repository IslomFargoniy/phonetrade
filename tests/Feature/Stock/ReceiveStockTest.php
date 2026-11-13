<?php

namespace Tests\Feature\Stock;

use App\Actions\Stock\ReceiveStock;
use App\Enums\Currency;
use App\Enums\StockStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\StockBatch;
use App\Models\StockUnit;
use App\Models\Supplier;
use App\Services\Cash\CashService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class ReceiveStockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function phoneLine(StoreFixture $store, int $count, array $overrides = []): array
    {
        return [
            'product_id' => $store->phone->id,
            'attributes' => ['Xotira' => '8/256 GB', 'Rang' => 'Qora', 'Holat' => 'Yangi'],
            'unit_cost' => '320',
            'sale_price' => '4850000',
            'sale_currency' => 'UZS',
            'max_discount_pct' => '15',
            'warranty_months' => 12,
            'units' => array_map(
                fn (int $n) => ['imei1' => StoreFixture::imei($n), 'imei2' => StoreFixture::imei($n + 500)],
                range(1, $count),
            ),
            ...$overrides,
        ];
    }

    public function test_ten_identical_phones_are_received_in_one_purchase(): void
    {
        $store = StoreFixture::create();
        $supplier = Supplier::query()->create(['name' => 'Malika bozori — Rustam aka']);

        $purchase = app(ReceiveStock::class)->handle($store->branch, $store->owner, [
            'type' => 'supplier',
            'currency' => 'USD',
            'supplier_id' => $supplier->id,
            'lines' => [$this->phoneLine($store, 10)],
        ]);

        $units = StockUnit::query()->orderBy('id')->get();

        $this->assertCount(10, $units);
        $this->assertSame('3200.00', $purchase->total);
        $this->assertSame('12750.00', $purchase->rate);
        $this->assertSame(10, $units->pluck('barcode')->unique()->count());
        $this->assertSame('TD-000001', $units->first()->barcode);
        $this->assertSame('TD-000010', $units->last()->barcode);

        $unit = $units->first();
        $this->assertSame('320.00', $unit->cost);
        $this->assertSame(Currency::USD, $unit->cost_currency);
        $this->assertSame('12750.00', $unit->cost_rate);
        $this->assertSame('15.00', $unit->max_discount_pct);
        $this->assertSame(StockStatus::InStock, $unit->status);
        $this->assertSame('Qora', $unit->attributes['Rang']);
    }

    public function test_barcodes_continue_across_purchases(): void
    {
        $store = StoreFixture::create();
        $action = app(ReceiveStock::class);

        $action->handle($store->branch, $store->owner, ['type' => 'opening', 'currency' => 'USD', 'lines' => [$this->phoneLine($store, 2)]]);
        $action->handle($store->branch, $store->owner, ['type' => 'opening', 'currency' => 'USD', 'lines' => [
            $this->phoneLine($store, 1, ['units' => [['imei1' => StoreFixture::imei(900)]]]),
        ]]);

        $this->assertSame(['TD-000001', 'TD-000002', 'TD-000003'], StockUnit::query()->orderBy('id')->pluck('barcode')->all());
    }

    public function test_opening_balance_does_not_touch_the_cash_accounts(): void
    {
        $store = StoreFixture::create();

        app(ReceiveStock::class)->handle($store->branch, $store->owner, [
            'type' => 'opening', 'currency' => 'USD', 'lines' => [$this->phoneLine($store, 1)],
        ]);

        $this->assertTrue(app(CashService::class)->balance($store->cashAccount(), Currency::USD)->isZero());
        $this->assertNull(Purchase::query()->firstOrFail()->supplier_id);
    }

    public function test_trade_in_pays_the_customer_from_the_chosen_cash_account(): void
    {
        $store = StoreFixture::create();
        $cash = app(CashService::class);
        $account = $store->cashAccount();
        $customer = Customer::query()->create(['full_name' => 'Aliyev Jasur', 'phone' => '998901234567']);
        $cash->deposit($account, Money::uzs(800000), 'sale');

        app(ReceiveStock::class)->handle($store->branch, $store->owner, [
            'type' => 'trade_in',
            'currency' => 'UZS',
            'customer_id' => $customer->id,
            'cash_account_id' => $account->id,
            'lines' => [$this->phoneLine($store, 1, ['unit_cost' => '500000', 'attributes' => ['Holat' => 'Ishlatilgan A']])],
        ]);

        $unit = StockUnit::query()->firstOrFail();

        // Mijozga berilgan summa — telefonning tannarxi; telefon darhol omborda.
        $this->assertSame('500000.00', $unit->cost);
        $this->assertSame(StockStatus::InStock, $unit->status);
        $this->assertSame('300000.00', $cash->balance($account, Currency::UZS)->amount);
    }

    public function test_trade_in_is_cancelled_entirely_when_cash_is_short(): void
    {
        $store = StoreFixture::create();
        $account = $store->cashAccount();
        $customer = Customer::query()->create(['full_name' => 'Aliyev Jasur', 'phone' => '998901234567']);
        app(CashService::class)->deposit($account, Money::uzs(499999), 'sale');

        try {
            app(ReceiveStock::class)->handle($store->branch, $store->owner, [
                'type' => 'trade_in',
                'currency' => 'UZS',
                'customer_id' => $customer->id,
                'cash_account_id' => $account->id,
                'lines' => [$this->phoneLine($store, 1, ['unit_cost' => '500000'])],
            ]);
            $this->fail('Kirim rad etilishi kerak edi.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('CASH_INSUFFICIENT', $exception->errorCode);
        }

        $this->assertSame(0, StockUnit::query()->count());
        $this->assertSame(0, Purchase::query()->count());
    }

    public function test_imei_must_be_valid_and_unique(): void
    {
        $store = StoreFixture::create();
        $action = app(ReceiveStock::class);
        $receive = fn (array $units) => $action->handle($store->branch, $store->owner, [
            'type' => 'opening', 'currency' => 'USD', 'lines' => [$this->phoneLine($store, 1, ['units' => $units])],
        ]);

        $codes = [];

        foreach ([
            [['imei1' => '123456789012345']],
            [['imei1' => StoreFixture::imei(1)], ['imei1' => StoreFixture::imei(1)]],
        ] as $units) {
            try {
                $receive($units);
            } catch (BusinessRuleException $exception) {
                $codes[] = $exception->errorCode;
            }
        }

        $receive([['imei1' => StoreFixture::imei(7), 'imei2' => StoreFixture::imei(8)]]);

        // Ikkinchi IMEI ham band hisoblanadi.
        try {
            $receive([['imei1' => StoreFixture::imei(8)]]);
        } catch (BusinessRuleException $exception) {
            $codes[] = $exception->errorCode;
        }

        $this->assertSame(['IMEI_INVALID', 'IMEI_DUPLICATE', 'IMEI_TAKEN'], $codes);
        $this->assertSame(1, StockUnit::query()->count());
    }

    public function test_accessories_are_received_as_batches_of_one_variant(): void
    {
        $store = StoreFixture::create();
        $action = app(ReceiveStock::class);
        $line = fn (string $cost, int $qty) => [
            'product_id' => $store->case->id,
            'attributes' => ['Rang' => 'Qora'],
            'barcode' => '4780012345678',
            'unit_cost' => $cost,
            'sale_price' => '60000',
            'sale_currency' => 'UZS',
            'qty' => $qty,
        ];

        $action->handle($store->branch, $store->owner, ['type' => 'opening', 'currency' => 'UZS', 'lines' => [$line('20000', 10)]]);
        $action->handle($store->branch, $store->owner, ['type' => 'opening', 'currency' => 'UZS', 'lines' => [$line('25000', 10)]]);

        $this->assertSame(1, ProductVariant::query()->count());
        $this->assertSame(['20000.00', '25000.00'], StockBatch::query()->orderBy('id')->pluck('unit_cost')->all());
        $this->assertSame(20, (int) StockBatch::query()->sum('qty_left'));
        $this->assertSame(0, StockUnit::query()->count());
    }

    public function test_dollar_purchase_requires_a_rate(): void
    {
        $store = StoreFixture::create();
        CurrencyRate::query()->delete();

        try {
            app(ReceiveStock::class)->handle($store->branch, $store->owner, [
                'type' => 'opening', 'currency' => 'USD', 'lines' => [$this->phoneLine($store, 1)],
            ]);
            $this->fail('Kurssiz dollarli kirim rad etilishi kerak edi.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('RATE_MISSING', $exception->errorCode);
        }
    }
}
