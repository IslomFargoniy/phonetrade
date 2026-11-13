<?php

namespace Tests\Feature\Api;

use App\Enums\Currency;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\Sale;
use App\Models\User;
use App\Services\Cash\CashService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private StoreFixture $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 14:00:00');
        $this->store = StoreFixture::create();
    }

    /**
     * Tayyorlash bosqichidagi amallar do'kon nomidan bajariladi; so'rovda kontekstni middleware o'rnatadi.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inStore(callable $callback): mixed
    {
        return app(TenantContext::class)->runFor($this->store->tenant, $callback);
    }

    private function actAs(User $user): void
    {
        app(TenantContext::class)->forget();
        Sanctum::actingAs($user);
    }

    public function test_login_returns_a_token_and_the_store_profile(): void
    {
        app(TenantContext::class)->forget();

        $response = $this->postJson('/api/v1/login', [
            'phone' => '+998 '.substr($this->store->owner->phone, 3),
            'password' => 'password',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.role', 'owner')
            ->assertJsonPath('store.branch.name', 'Chilonzor')
            ->assertJsonPath('store.rate', '12750.00')
            ->assertJsonCount(4, 'store.installment_rates');

        $token = $response->json('token');
        $this->assertIsString($token);

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.id', $this->store->owner->id);
    }

    public function test_wrong_password_and_superadmin_are_refused(): void
    {
        app(TenantContext::class)->forget();
        $admin = User::factory()->superadmin()->create();

        $this->postJson('/api/v1/login', ['phone' => $this->store->owner->phone, 'password' => 'xato', 'device_name' => 'x'])
            ->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');

        $this->postJson('/api/v1/login', ['phone' => $admin->phone, 'password' => 'password', 'device_name' => 'x'])
            ->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_DISABLED');

        $this->getJson('/api/v1/me')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_seller_scans_previews_and_closes_a_sale(): void
    {
        $phone = $this->inStore(fn () => $this->store->receivePhone(1));
        $seller = $this->inStore(fn () => $this->store->seller());
        $this->actAs($seller);

        $lookup = $this->getJson('/api/v1/stock/lookup?code='.$phone->imei1);
        $lookup->assertOk()->assertJsonPath('exact', true)->assertJsonPath('units.0.id', $phone->id);
        $this->assertArrayNotHasKey('cost', $lookup->json('units.0'));

        $cart = ['items' => [['stock_unit_id' => $phone->id, 'discount' => '150000']]];

        $this->postJson('/api/v1/sales/preview', $cart)->assertOk()->assertJsonPath('total', '4700000.00');

        $response = $this->postJson('/api/v1/sales', [
            ...$cart,
            'payments' => [['method' => 'cash', 'currency' => 'UZS', 'amount' => '4700000']],
        ], ['Idempotency-Key' => 'sale-1']);

        $response->assertCreated()->assertJsonPath('total_label', "4 700 000 so'm");
        $this->assertArrayNotHasKey('profit_label', $response->json());

        $this->getJson('/api/v1/sales/'.$response->json('id'))->assertOk()->assertJsonPath('number', 1);
    }

    public function test_repeated_request_with_the_same_key_does_not_charge_twice(): void
    {
        $phone = $this->inStore(fn () => $this->store->receivePhone(1));
        $this->actAs($this->store->owner);

        $payload = [
            'items' => [['stock_unit_id' => $phone->id]],
            'payments' => [['method' => 'cash', 'currency' => 'UZS', 'amount' => '4850000']],
        ];

        $first = $this->postJson('/api/v1/sales', $payload, ['Idempotency-Key' => 'abc-123']);
        $second = $this->postJson('/api/v1/sales', $payload, ['Idempotency-Key' => 'abc-123']);

        $first->assertCreated();
        $second->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->json('id'), $second->json('id'));

        $this->assertSame(1, Sale::query()->count());
        $balance = $this->inStore(fn () => app(CashService::class)->balance($this->store->cashAccount(), Currency::UZS));
        $this->assertSame('4850000.00', $balance->amount);
    }

    public function test_money_requests_need_an_idempotency_key(): void
    {
        $phone = $this->inStore(fn () => $this->store->receivePhone(1));
        $this->actAs($this->store->owner);

        $this->postJson('/api/v1/sales', [
            'items' => [['stock_unit_id' => $phone->id]],
            'payments' => [['method' => 'cash', 'currency' => 'UZS', 'amount' => '4850000']],
        ])->assertStatus(400)->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_business_and_validation_errors_carry_a_stable_code(): void
    {
        $phone = $this->inStore(fn () => $this->store->receivePhone(1));
        $this->actAs($this->store->owner);

        $this->postJson('/api/v1/sales', [
            'items' => [['stock_unit_id' => $phone->id]],
            'payments' => [['method' => 'cash', 'currency' => 'UZS', 'amount' => '1000']],
        ], ['Idempotency-Key' => 'k1'])->assertStatus(422)->assertJsonPath('code', 'PAYMENT_NOT_BALANCED');

        $this->postJson('/api/v1/sales', ['items' => []], ['Idempotency-Key' => 'k2'])
            ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_FAILED');

        $this->postJson('/api/v1/sales/preview', ['items' => [['stock_unit_id' => $phone->id, 'discount' => '900000']]])
            ->assertStatus(422)->assertJsonPath('code', 'DISCOUNT_LIMIT_EXCEEDED');
    }

    public function test_instalment_payment_is_accepted_through_the_api(): void
    {
        $unit = $this->inStore(fn () => $this->store->receivePhone(1, ['unit_cost' => '450000', 'sale_price' => '500000', 'max_discount_pct' => '0'], 'UZS'));
        $this->inStore(function (): void {
            $customer = Customer::query()->create(['full_name' => 'Aliyev Jasur', 'phone' => '998901234567']);
            $customer->files()->create(['path' => 'customers/x.jpg']);
        });
        $this->actAs($this->store->owner);

        $this->postJson('/api/v1/sales', [
            'mode' => 'installment',
            'items' => [['stock_unit_id' => $unit->id]],
            'installment' => ['currency' => 'UZS', 'months' => 3, 'pay_day' => 13, 'down_payment' => '0'],
            'payments' => [['method' => 'cash', 'currency' => 'UZS', 'amount' => '200000']],
            'customer_phone' => '90 123 45 67', 'customer_name' => 'Aliyev Jasur',
            'passport_series' => 'AB', 'passport_number' => '1234567', 'pinfl' => '12345678901234',
            'customer_address' => 'Chilonzor',
        ], ['Idempotency-Key' => 'contract-1'])->assertCreated()->assertJsonPath('type', 'installment');

        $plan = InstallmentPlan::query()->firstOrFail();

        $this->getJson('/api/v1/installments')->assertOk()->assertJsonPath('plans.0.remaining', '400000.00');

        $this->postJson("/api/v1/installments/{$plan->id}/payments", ['amount' => '200000', 'currency' => 'UZS', 'method' => 'cash'], ['Idempotency-Key' => 'pay-1'])
            ->assertCreated()->assertJsonPath('remaining', '200000.00');

        // Tarmoq uzilib, ilova qayta yuborsa — ikkinchi marta yozilmaydi.
        $this->postJson("/api/v1/installments/{$plan->id}/payments", ['amount' => '200000', 'currency' => 'UZS', 'method' => 'cash'], ['Idempotency-Key' => 'pay-1'])
            ->assertCreated()->assertJsonPath('remaining', '200000.00');

        $this->assertSame('400000.00', $plan->fresh()->paid_total);
    }

    public function test_owner_endpoints_are_closed_to_sellers_and_other_stores(): void
    {
        $phone = $this->inStore(fn () => $this->store->receivePhone(1));
        $seller = $this->inStore(fn () => $this->store->seller());
        $stranger = StoreFixture::create()->owner;

        $this->actAs($seller);
        $this->getJson('/api/v1/reports/summary')->assertForbidden();
        $this->getJson('/api/v1/cash')->assertForbidden();

        $this->actAs($this->store->owner);
        $this->getJson('/api/v1/reports/summary?period=day')->assertOk()->assertJsonPath('sales_count', 0)->assertJsonCount(6, 'expected_profit');
        $this->getJson('/api/v1/cash')->assertOk()->assertJsonCount(3, 'branch_accounts')->assertJsonCount(3, 'admin_accounts');

        // Boshqa do'kon egasi bu telefonni topa olmaydi.
        $this->actAs($stranger);
        $this->getJson('/api/v1/stock/lookup?code='.$phone->imei1)->assertOk()->assertJsonCount(0, 'units');
    }

    public function test_blocked_store_loses_api_access(): void
    {
        app(TenantContext::class)->forget();
        $token = $this->store->owner->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $this->store->tenant->update(['status' => 'blocked']);
        app('auth')->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_demo_login_works_only_when_configured_and_only_for_the_demo_user(): void
    {
        $seller = $this->inStore(fn () => $this->store->seller());
        app(TenantContext::class)->forget();

        // Sozlanmagan: demo rejimi yo'q.
        $this->postJson('/api/v1/demo-login')->assertNotFound()->assertJsonPath('code', 'DEMO_UNAVAILABLE');

        config(['app.demo_phone' => $seller->phone]);

        $token = $this->postJson('/api/v1/demo-login')
            ->assertOk()
            ->assertJsonPath('user.id', $seller->id)
            ->assertJsonPath('user.role', 'seller')
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/stock')->assertOk();

        // Demo akkauntni tashrif buyuruvchi o'chira olmaydi.
        $this->withToken($token)->deleteJson('/api/v1/account', ['password' => 'password'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ACCOUNT_NOT_DELETABLE');
        $this->assertTrue($seller->refresh()->is_active);

        // O'chirilgan demo xodim bilan kirib bo'lmaydi.
        $seller->update(['is_active' => false]);
        $this->postJson('/api/v1/demo-login')->assertNotFound();
    }
}
