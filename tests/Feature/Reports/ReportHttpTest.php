<?php

namespace Tests\Feature\Reports;

use App\Actions\Sales\CloseSale;
use App\Enums\Currency;
use App\Enums\StockStatus;
use App\Models\CashAccount;
use App\Models\ExpenseCategory;
use App\Models\InstallmentPlan;
use App\Models\Sale;
use App\Services\Cash\CashService;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class ReportHttpTest extends TestCase
{
    use RefreshDatabase;

    private StoreFixture $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 14:00:00');
        $this->store = StoreFixture::create();
    }

    private function sale(): Sale
    {
        return app(TenantContext::class)->runFor($this->store->tenant, fn () => app(CloseSale::class)->handle(
            $this->store->branch, $this->store->owner,
            [['stock_unit_id' => $this->store->receivePhone(1)->id, 'discount' => '150000']],
            [['method' => 'cash', 'currency' => 'UZS', 'amount' => '4700000']],
        ));
    }

    public function test_reports_are_for_the_owner_only(): void
    {
        $seller = $this->store->seller();
        $this->sale();
        app(TenantContext::class)->forget();

        $this->actingAs($seller)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($seller)->get(route('reports.export'))->assertForbidden();

        $this->actingAs($this->store->owner)->get(route('reports.index', ['period' => 'day']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/index')
                ->where('kpis.0.value', "4 700 000 so'm")
                ->where('kpis.1.value', "620 000 so'm")
                ->where('kpis.2.value', "620 000 so'm")
                ->has('expected', 6)
                ->where('sellers.0.sales', 1)
                ->where('cash.branch.0.uzs', "4 700 000 so'm"));
    }

    public function test_sales_can_be_exported_for_excel(): void
    {
        $this->sale();
        app(TenantContext::class)->forget();

        $response = $this->actingAs($this->store->owner)->get(route('reports.export', ['period' => 'day']));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Samsung Galaxy A55', $csv);
        $this->assertStringContainsString('4700000.00', $csv);
        $this->assertStringContainsString('620000.00', $csv);
    }

    public function test_owner_records_an_expense_and_sweeps_the_cash(): void
    {
        $seller = $this->store->seller();
        $this->sale();
        $category = ExpenseCategory::query()->create(['name' => 'Ijara']);
        $account = $this->store->cashAccount();
        app(TenantContext::class)->forget();

        $expense = ['expense_category_id' => $category->id, 'account' => "{$account->id}:UZS", 'amount' => '700000', 'note' => 'Noyabr'];

        $this->actingAs($seller)->post(route('cash.expenses.store'), $expense)->assertForbidden();
        $this->actingAs($seller)->post(route('cash.sweep'))->assertForbidden();
        $this->actingAs($seller)->get(route('cash.index', ['scope' => 'admin']))
            ->assertInertia(fn (Assert $page) => $page->where('scope', 'branch')->where('canManage', false));

        $this->actingAs($this->store->owner)->post(route('cash.expenses.store'), $expense)->assertSessionHasNoErrors();
        $this->actingAs($this->store->owner)->post(route('cash.expenses.store'), [...$expense, 'amount' => '99000000'])
            ->assertSessionHasErrors('cash_account_id');

        $this->actingAs($this->store->owner)->post(route('cash.sweep'))->assertSessionHasNoErrors();

        $cash = app(CashService::class);
        $admin = CashAccount::query()->withoutGlobalScopes()->where('tenant_id', $this->store->tenant->id)->whereNull('branch_id')->where('type', 'cash')->firstOrFail();

        $this->assertTrue($cash->balance($account, Currency::UZS)->isZero());
        $this->assertTrue($cash->balance($admin, Currency::UZS)->equals(Money::uzs(4000000)));

        $this->actingAs($this->store->owner)->get(route('cash.index', ['scope' => 'admin']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('cash/index')
                ->where('scope', 'admin')
                ->where('accounts.0.uzs_label', "4 000 000 so'm"));

        $this->actingAs($this->store->owner)->post(route('cash.sweep'))->assertSessionHasErrors('branch');
    }

    public function test_imei_history_follows_a_phone_from_receipt_to_sale(): void
    {
        $sale = $this->sale();
        $seller = $this->store->seller();
        $imei = StoreFixture::imei(1);
        app(TenantContext::class)->forget();

        $this->actingAs($seller)->get(route('stock.history', ['code' => $imei]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/history')
                ->where('unit.imei1', $imei)
                ->missing('unit.cost')
                ->has('events', 2)
                ->where('events.0.title', "Kirim · Boshlang'ich qoldiq")
                ->where('events.1.title', "Sotuv · chek №{$sale->number}")
                ->where('events.1.sale_id', $sale->id));

        $this->actingAs($seller)->get(route('stock.history', ['code' => '000']))
            ->assertInertia(fn (Assert $page) => $page->where('unit', null));
    }

    public function test_ledger_check_passes_on_consistent_data_and_reports_a_broken_contract(): void
    {
        $this->sale();
        app(TenantContext::class)->forget();

        $this->artisan('ledger:check')->assertSuccessful();

        // Sotilgan deb belgilangan, lekin savdosi yo'q telefon.
        app(TenantContext::class)->runFor($this->store->tenant, fn () => $this->store->receivePhone(7)->update(['status' => StockStatus::Sold]));

        $this->artisan('ledger:check')->assertFailed();
        $this->assertSame(0, InstallmentPlan::query()->count());
    }
}
