<?php

namespace Tests\Feature\Installments;

use App\Actions\Installments\AcceptInstallmentPayment;
use App\Actions\Sales\CloseSale;
use App\Actions\Sales\ReturnSale;
use App\Enums\CashAccountType;
use App\Enums\Currency;
use App\Enums\PlanStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Enums\StockStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\Sale;
use App\Services\Cash\CashService;
use App\Services\Installments\InstallmentTerms;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\StoreFixture;
use Tests\TestCase;

/**
 * Texnik rejadagi misol: 500 000 so'mlik telefon, tannarxi 450 000, 3 oy, 20%.
 */
class InstallmentSaleTest extends TestCase
{
    use RefreshDatabase;

    private StoreFixture $store;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 14:00:00');
        $this->store = StoreFixture::create();
        $this->customer = Customer::query()->create(['full_name' => 'Aliyev Jasur', 'phone' => '998901234567']);
    }

    private function phone(int $n = 1): int
    {
        return $this->store->receivePhone($n, ['unit_cost' => '450000', 'sale_price' => '500000', 'max_discount_pct' => '0'], 'UZS')->id;
    }

    /**
     * @param  list<array{method: string, currency: string, amount: string}>  $payments
     */
    private function sell(string $down, array $payments, Currency $currency = Currency::UZS, int $months = 3): Sale
    {
        return app(CloseSale::class)->handle(
            $this->store->branch,
            $this->store->owner,
            [['stock_unit_id' => $this->phone()]],
            $payments,
            $this->customer->id,
            new InstallmentTerms($currency, $months, 13, $down),
        );
    }

    private function pay(InstallmentPlan $plan, Money $amount, CashAccountType $method = CashAccountType::Cash): void
    {
        app(AcceptInstallmentPayment::class)->handle($plan, $this->store->branch, $this->store->owner, $amount, $method);
    }

    public function test_mode_a_contract_takes_the_first_instalment_at_the_sale(): void
    {
        $sale = $this->sell('0', [['method' => 'cash', 'currency' => 'UZS', 'amount' => '200000']]);
        $plan = $sale->plan()->firstOrFail();

        $this->assertSame(SaleType::Installment, $sale->type);
        $this->assertSame('500000.00', $sale->total);
        $this->assertSame('A', $plan->mode);
        $this->assertSame('600000.00', $plan->total_debt);
        $this->assertSame('600000.00', $plan->total_revenue);
        $this->assertSame('150000.00', $plan->total_profit);
        $this->assertSame('0.2500000000', $plan->profit_ratio);
        $this->assertSame('200000.00', $plan->paid_total);
        $this->assertSame('400000.00', $plan->remaining()->amount);

        $this->assertSame(
            [['2026-11-05', '200000.00', '200000.00'], ['2026-12-13', '200000.00', '0.00'], ['2027-01-13', '200000.00', '0.00']],
            $plan->schedule()->orderBy('no')->get()->map(fn ($row) => [$row->due_date->toDateString(), $row->amount, $row->paid_amount])->all(),
        );

        // 200 000 ning 25% i foyda.
        $this->assertSame('50000.00', $plan->payments()->firstOrFail()->profit_amount);
        $this->assertSame('200000.00', app(CashService::class)->balance($this->store->cashAccount(), Currency::UZS)->amount);
        $this->assertSame(StockStatus::Sold, $sale->items()->firstOrFail()->unit->status);
    }

    public function test_mode_b_contract_takes_the_down_payment_and_schedules_from_next_month(): void
    {
        $sale = $this->sell('100000', [['method' => 'cash', 'currency' => 'UZS', 'amount' => '100000']]);
        $plan = $sale->plan()->firstOrFail();

        $this->assertSame('B', $plan->mode);
        $this->assertSame('480000.00', $plan->total_debt);
        $this->assertSame('580000.00', $plan->total_revenue);
        $this->assertSame('130000.00', $plan->total_profit);
        $this->assertSame('22413.79', $plan->payments()->firstOrFail()->profit_amount);
        $this->assertSame(
            [['2026-12-13', '160000.00', '0.00'], ['2027-01-13', '160000.00', '0.00'], ['2027-02-13', '160000.00', '0.00']],
            $plan->schedule()->orderBy('no')->get()->map(fn ($row) => [$row->due_date->toDateString(), $row->amount, $row->paid_amount])->all(),
        );
    }

    public function test_payment_below_the_amount_due_today_is_rejected(): void
    {
        try {
            $this->sell('0', [['method' => 'cash', 'currency' => 'UZS', 'amount' => '199999']]);
            $this->fail('Savdo rad etilishi kerak edi.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('PAYMENT_NOT_BALANCED', $exception->errorCode);
        }

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, InstallmentPlan::query()->count());
    }

    public function test_instalment_sale_requires_a_customer(): void
    {
        $this->expectException(BusinessRuleException::class);

        app(CloseSale::class)->handle(
            $this->store->branch, $this->store->owner,
            [['stock_unit_id' => $this->phone()]],
            [['method' => 'cash', 'currency' => 'UZS', 'amount' => '200000']],
            null,
            new InstallmentTerms(Currency::UZS, 3, 13),
        );
    }

    public function test_payments_fill_the_oldest_instalment_first_and_profit_adds_up_exactly(): void
    {
        $plan = $this->sell('100000', [['method' => 'cash', 'currency' => 'UZS', 'amount' => '100000']])->plan()->firstOrFail();

        // Qisman, keyin ortig'i bilan: 100 000 + 250 000 + 130 000 = 480 000
        $this->pay($plan, Money::uzs(100000));
        $this->assertSame(['100000.00', '0.00', '0.00'], $plan->schedule()->orderBy('no')->pluck('paid_amount')->all());

        $this->pay($plan, Money::uzs(250000), CashAccountType::Card);
        $this->assertSame(['160000.00', '160000.00', '30000.00'], $plan->schedule()->orderBy('no')->pluck('paid_amount')->all());
        $this->assertSame(PlanStatus::Active, $plan->fresh()->status);

        $this->pay($plan, Money::uzs(130000));

        $plan->refresh();
        $this->assertSame(PlanStatus::Closed, $plan->status);
        $this->assertSame('580000.00', $plan->paid_total);
        $this->assertTrue($plan->remaining()->isZero());

        // To'lovlardan yig'ilgan foyda shartnoma foydasiga tiyingacha teng.
        $this->assertSame('130000.00', Money::uzs((string) $plan->payments()->sum('profit_amount'))->amount);

        $cash = app(CashService::class);
        $this->assertSame('330000.00', $cash->balance($this->store->cashAccount(CashAccountType::Cash), Currency::UZS)->amount);
        $this->assertSame('250000.00', $cash->balance($this->store->cashAccount(CashAccountType::Card), Currency::UZS)->amount);
    }

    public function test_paying_early_does_not_reduce_the_markup(): void
    {
        $plan = $this->sell('0', [['method' => 'cash', 'currency' => 'UZS', 'amount' => '200000']])->plan()->firstOrFail();

        // Qolgan 400 000 bir yo'la to'lanadi: jami baribir 600 000.
        $this->pay($plan, Money::uzs(400000));

        $this->assertSame(PlanStatus::Closed, $plan->fresh()->status);
        $this->assertSame('600000.00', $plan->fresh()->paid_total);
    }

    public function test_more_than_the_remaining_debt_is_not_accepted(): void
    {
        $plan = $this->sell('0', [['method' => 'cash', 'currency' => 'UZS', 'amount' => '200000']])->plan()->firstOrFail();

        try {
            $this->pay($plan, Money::uzs(400001));
            $this->fail('To\'lov rad etilishi kerak edi.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('PAYMENT_EXCEEDS_DEBT', $exception->errorCode);
        }

        $this->assertSame('200000.00', $plan->fresh()->paid_total);
    }

    public function test_dollar_debt_can_be_paid_in_sum_at_the_payment_day_rate(): void
    {
        $store = $this->store;
        $unit = $store->receivePhone(9, ['unit_cost' => '400', 'sale_price' => '460', 'sale_currency' => 'USD', 'max_discount_pct' => '0']);

        $sale = app(CloseSale::class)->handle($store->branch, $store->owner, [['stock_unit_id' => $unit->id]], [
            ['method' => 'cash', 'currency' => 'USD', 'amount' => '60'],
        ], $this->customer->id, new InstallmentTerms(Currency::USD, 12, 13));
        $plan = $sale->plan()->firstOrFail();

        // 460 × 1,55 = 713; oylik 60 $ (10 $ gacha yaxlitlangan)
        $this->assertSame(Currency::USD, $plan->currency);
        $this->assertSame('713.00', $plan->total_debt);

        $store->setRate('13000');
        $this->pay($plan, Money::uzs(780000));

        // 780 000 / 13 000 = 60 $ — to'lov kunidagi kurs bilan.
        $this->assertSame('120.00', $plan->fresh()->paid_total);
        $this->assertSame('780000.00', app(CashService::class)->balance($store->cashAccount(), Currency::UZS)->amount);
    }

    public function test_mixed_currency_cart_forces_a_sum_contract(): void
    {
        $store = $this->store;
        $iphone = $store->receivePhone(9, ['unit_cost' => '400', 'sale_price' => '460', 'sale_currency' => 'USD']);
        $case = $store->receiveCases(5);

        $sale = app(CloseSale::class)->handle($store->branch, $store->owner, [
            ['stock_unit_id' => $iphone->id],
            ['variant_id' => $case->id, 'qty' => 1],
        ], [['method' => 'cash', 'currency' => 'UZS', 'amount' => '2370000']], $this->customer->id, new InstallmentTerms(Currency::USD, 3, 13));

        $plan = $sale->plan()->firstOrFail();

        // 5 925 000 × 1,2 = 7 110 000; oylik 2 370 000
        $this->assertSame(Currency::UZS, $plan->currency);
        $this->assertSame('7110000.00', $plan->total_debt);
    }

    public function test_returning_an_instalment_sale_cancels_the_contract_and_refunds_what_was_paid(): void
    {
        $sale = $this->sell('0', [['method' => 'cash', 'currency' => 'UZS', 'amount' => '200000']]);
        $plan = $sale->plan()->firstOrFail();
        $this->pay($plan, Money::uzs(100000));

        $return = app(ReturnSale::class)->handle($sale, $this->store->owner, [], CashAccountType::Cash, 'Nuqson');

        $this->assertSame('300000.00', $return->refund_amount);
        $this->assertSame('75000.00', $return->profit_reversed);
        $this->assertSame(PlanStatus::Cancelled, $plan->fresh()->status);
        $this->assertSame(SaleStatus::Returned, $sale->fresh()->status);
        $this->assertSame(StockStatus::Returned, $sale->items()->firstOrFail()->unit->status);
        $this->assertTrue(app(CashService::class)->balance($this->store->cashAccount(), Currency::UZS)->isZero());

        $this->expectException(BusinessRuleException::class);
        $this->pay($plan, Money::uzs(100000));
    }
}
