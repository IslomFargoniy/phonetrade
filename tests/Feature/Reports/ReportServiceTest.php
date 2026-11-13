<?php

namespace Tests\Feature\Reports;

use App\Actions\Cash\RecordExpense;
use App\Actions\Cash\SweepCash;
use App\Actions\Installments\AcceptInstallmentPayment;
use App\Actions\Sales\CloseSale;
use App\Actions\Sales\ReturnSale;
use App\Enums\CashAccountType;
use App\Enums\Currency;
use App\Exceptions\BusinessRuleException;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Sale;
use App\Services\Cash\CashService;
use App\Services\Installments\InstallmentTerms;
use App\Services\Reports\ReportService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private StoreFixture $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 14:00:00');
        $this->store = StoreFixture::create();
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::today();
    }

    /**
     * To'liq savdo: 4 850 000 − 150 000 chegirma; tannarx $320 × 12 750 → foyda 620 000.
     */
    private function fullSale(int $n = 1): Sale
    {
        return app(CloseSale::class)->handle($this->store->branch, $this->store->owner,
            [['stock_unit_id' => $this->store->receivePhone($n)->id, 'discount' => '150000']],
            [['method' => 'cash', 'currency' => 'UZS', 'amount' => '4700000']],
        );
    }

    /**
     * Nasiya: 500 000 lik telefon (tannarx 450 000), 3 oy, 20% → 1-oylik 200 000, foyda ulushi 25%.
     */
    private function installmentSale(int $n = 50): Sale
    {
        $customer = Customer::query()->firstOrCreate(['phone' => '998901234567'], ['full_name' => 'Aliyev Jasur']);
        $unit = $this->store->receivePhone($n, ['unit_cost' => '450000', 'sale_price' => '500000', 'max_discount_pct' => '0'], 'UZS');

        return app(CloseSale::class)->handle($this->store->branch, $this->store->owner,
            [['stock_unit_id' => $unit->id]],
            [['method' => 'cash', 'currency' => 'UZS', 'amount' => '200000']],
            $customer->id,
            new InstallmentTerms(Currency::UZS, 3, 13),
        );
    }

    private function expense(string $amount): void
    {
        $category = ExpenseCategory::query()->firstOrCreate(['name' => 'Ijara']);

        app(RecordExpense::class)->handle($this->store->branch, $this->store->owner, $category->id, $this->store->cashAccount()->id, Money::uzs($amount), 'Noyabr');
    }

    public function test_summary_combines_full_sales_instalment_payments_and_expenses(): void
    {
        $this->fullSale();
        $this->installmentSale();
        $this->expense('100000');

        $summary = app(ReportService::class)->summary($this->today(), $this->today());

        $this->assertSame(2, $summary['sales_count']);
        $this->assertSame('5200000.00', $summary['revenue']->amount);
        $this->assertSame('150000.00', $summary['discounts']->amount);
        // 620 000 (to'liq savdo) + 50 000 (200 000 lik nasiya to'lovining 25%)
        $this->assertSame('670000.00', $summary['gross_profit']->amount);
        $this->assertSame('100000.00', $summary['expenses']->amount);
        $this->assertSame('570000.00', $summary['net_profit']->amount);
        $this->assertSame('200000.00', $summary['installment_income']->amount);
        $this->assertSame(1, $summary['installment_payments']);
        $this->assertSame('400000.00', $summary['active_debt']->amount);
        $this->assertSame(1, $summary['active_plans']);
    }

    public function test_instalment_profit_is_counted_in_the_month_the_money_arrives(): void
    {
        $plan = $this->installmentSale()->plan()->firstOrFail();

        $this->travelTo('2026-12-13 10:00:00');
        app(AcceptInstallmentPayment::class)->handle($plan, $this->store->branch, $this->store->owner, Money::uzs(200000), CashAccountType::Cash);

        $november = app(ReportService::class)->summary(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'));
        $december = app(ReportService::class)->summary(CarbonImmutable::parse('2026-12-01'), CarbonImmutable::parse('2026-12-31'));

        $this->assertSame('50000.00', $november['gross_profit']->amount);
        $this->assertSame('50000.00', $december['gross_profit']->amount);
        $this->assertSame(0, $december['sales_count']);
        $this->assertSame('200000.00', $december['installment_income']->amount);
    }

    public function test_returned_sale_takes_its_profit_back(): void
    {
        $sale = $this->fullSale();

        app(ReturnSale::class)->handle($sale, $this->store->owner, [['sale_item_id' => $sale->items()->firstOrFail()->id, 'qty' => 1]], CashAccountType::Cash);

        $this->assertTrue(app(ReportService::class)->summary($this->today(), $this->today())['gross_profit']->isZero());
    }

    public function test_expected_profit_is_spread_over_the_months_of_the_schedule(): void
    {
        // Qolgan qarz 400 000 × 25% = 100 000: dekabr va yanvarga 50 000 dan.
        $this->installmentSale();

        $expected = app(ReportService::class)->expectedProfit(3);

        $this->assertSame(
            [['2026-11', '0.00'], ['2026-12', '50000.00'], ['2027-01', '50000.00']],
            array_map(fn (array $row) => [$row['month'], $row['profit']->amount], $expected),
        );

        // Muddati o'tgan oylik joriy oyga qo'shiladi.
        $this->travelTo('2027-01-20 10:00:00');
        $late = app(ReportService::class)->expectedProfit(2);

        $this->assertSame('100000.00', $late[0]['profit']->amount);
    }

    public function test_sweep_moves_every_balance_to_the_owners_admin_accounts(): void
    {
        $cash = app(CashService::class);
        $cash->deposit($this->store->cashAccount(CashAccountType::Cash), Money::uzs(8450000), 'sale');
        $cash->deposit($this->store->cashAccount(CashAccountType::Cash), Money::usd(1200), 'sale');
        $cash->deposit($this->store->cashAccount(CashAccountType::Card), Money::uzs(3120000), 'sale');

        app(SweepCash::class)->handle($this->store->branch, $this->store->owner);

        foreach (CashAccountType::cases() as $type) {
            foreach (Currency::cases() as $currency) {
                $this->assertTrue($cash->balance($this->store->cashAccount($type), $currency)->isZero());
            }
        }

        $admin = fn (CashAccountType $type) => CashAccount::query()->whereNull('branch_id')->where('type', $type)->firstOrFail();

        $this->assertSame('8450000.00', $cash->balance($admin(CashAccountType::Cash), Currency::UZS)->amount);
        $this->assertSame('1200.00', $cash->balance($admin(CashAccountType::Cash), Currency::USD)->amount);
        $this->assertSame('3120000.00', $cash->balance($admin(CashAccountType::Card), Currency::UZS)->amount);

        // Bo'sh kassani bo'shatib bo'lmaydi.
        $this->expectException(BusinessRuleException::class);
        app(SweepCash::class)->handle($this->store->branch, $this->store->owner);
    }

    public function test_expense_cannot_exceed_the_cash_balance(): void
    {
        app(CashService::class)->deposit($this->store->cashAccount(), Money::uzs(50000), 'sale');

        try {
            $this->expense('50001');
            $this->fail('Xarajat rad etilishi kerak edi.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('CASH_INSUFFICIENT', $exception->errorCode);
        }

        $this->assertSame(0, Expense::query()->count());

        $this->expense('50000');
        $this->assertTrue(app(CashService::class)->balance($this->store->cashAccount(), Currency::UZS)->isZero());
    }

    public function test_cash_report_shows_opening_movement_and_closing_balances(): void
    {
        $cash = app(CashService::class);
        $account = $this->store->cashAccount();

        $this->travelTo('2026-11-04 10:00:00');
        $cash->deposit($account, Money::uzs(1000000), 'sale');

        $this->travelTo('2026-11-05 10:00:00');
        $cash->deposit($account, Money::uzs(500000), 'sale');
        $cash->withdraw($account, Money::uzs(200000), 'expense');

        $row = collect(app(ReportService::class)->cash($this->today(), $this->today(), $this->store->branch->id))
            ->first(fn (array $row) => $row['account_id'] === $account->id && $row['currency'] === Currency::UZS);

        $this->assertSame(['1000000.00', '500000.00', '200000.00', '1300000.00'], [
            $row['opening']->amount, $row['in']->amount, $row['out']->amount, $row['closing']->amount,
        ]);
    }

    public function test_stock_report_values_cost_at_the_purchase_rate(): void
    {
        $this->store->receivePhone(1);
        $this->store->receiveCases(10, '20000');
        $this->store->setRate('13000');

        $stock = app(ReportService::class)->stock();

        $this->assertSame(1, $stock['units']);
        $this->assertSame(10, $stock['accessories']);
        // $320 × 12 750 (kirim kursi) + 10 × 20 000
        $this->assertSame('4280000.00', $stock['cost']->amount);
        $this->assertSame('5450000.00', $stock['value']->amount);
        $this->assertSame(1, $stock['aging']['fresh']);
    }

    public function test_sales_are_broken_down_by_seller_and_product(): void
    {
        $this->fullSale(1);
        $this->fullSale(2);

        $report = app(ReportService::class);
        $sellers = $report->salesBySeller($this->today(), $this->today());
        $products = $report->salesByProduct($this->today(), $this->today());

        $this->assertSame([2, '9400000.00', '1240000.00'], [$sellers[0]['sales'], $sellers[0]['revenue']->amount, $sellers[0]['profit']->amount]);
        $this->assertSame(['Samsung Galaxy A55', 2], [$products[0]['name'], $products[0]['qty']]);
    }
}
