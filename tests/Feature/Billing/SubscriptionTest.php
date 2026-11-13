<?php

namespace Tests\Feature\Billing;

use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\Subscription;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private StoreFixture $store;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 14:00:00');
        $this->store = StoreFixture::create();
        $this->plan = Plan::query()->create(['name' => 'Kichik', 'price' => '200000', 'max_branches' => 1, 'max_users' => 1]);

        app(TenantContext::class)->forget();
    }

    private function subscribe(?string $paidUntil, bool $withPlan = true): void
    {
        $this->store->tenant->forceFill([
            'plan_id' => $withPlan ? $this->plan->id : null,
            'paid_until' => $paidUntil,
        ])->save();
    }

    private function tenant(): Tenant
    {
        return $this->store->tenant->refresh();
    }

    private function currentRate(): string
    {
        return (string) CurrencyRate::query()->where('tenant_id', $this->store->tenant->id)->latest('id')->value('usd_uzs');
    }

    public function test_state_follows_the_paid_date_and_the_grace_period(): void
    {
        $subscription = app(Subscription::class);

        $this->assertSame('unlimited', $subscription->state($this->tenant()));

        $this->subscribe('2026-11-05');
        $this->assertSame('active', $subscription->state($this->tenant()));
        $this->assertSame(0, $subscription->daysLeft($this->tenant()));

        // Muddatdan keyingi 5 kun — imtiyozli davr.
        $this->travelTo('2026-11-06 09:00:00');
        $this->assertSame('grace', $subscription->state($this->tenant()));
        $this->travelTo('2026-11-10 23:00:00');
        $this->assertSame('grace', $subscription->state($this->tenant()));
        $this->assertFalse($subscription->isReadOnly($this->tenant()));

        $this->travelTo('2026-11-11 00:30:00');
        $this->assertSame('expired', $subscription->state($this->tenant()));
        $this->assertTrue($subscription->isReadOnly($this->tenant()));
        $this->assertSame('11.11.2026', $subscription->readOnlyFrom($this->tenant())?->format('d.m.Y'));
    }

    public function test_expired_store_can_read_but_not_write(): void
    {
        $this->subscribe('2026-10-01');
        $before = $this->currentRate();

        $this->actingAs($this->store->owner)->get(route('store-settings.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('store.subscription.state', 'expired'));

        $this->actingAs($this->store->owner)
            ->from(route('store-settings.edit'))
            ->put(route('store-settings.rate'), ['usd_uzs' => '13000'])
            ->assertRedirect(route('store-settings.edit'))
            ->assertSessionHasErrors('business');

        $this->assertSame($before, $this->currentRate());

        // Chiqish yopilmaydi.
        $this->actingAs($this->store->owner)->post(route('logout'))->assertSessionHasNoErrors();
    }

    public function test_expired_store_api_writes_get_a_stable_code(): void
    {
        $this->subscribe('2026-10-01');
        Sanctum::actingAs($this->store->owner);

        $this->getJson('/api/v1/me')->assertOk();
        $this->getJson('/api/v1/stock')->assertOk();

        $this->postJson('/api/v1/sales', ['items' => [['stock_unit_id' => 1]]], ['Idempotency-Key' => 'k-1'])
            ->assertStatus(402)
            ->assertJsonPath('code', 'SUBSCRIPTION_EXPIRED');
    }

    public function test_writes_keep_working_during_the_grace_period(): void
    {
        $this->subscribe('2026-11-02');

        $this->actingAs($this->store->owner)
            ->put(route('store-settings.rate'), ['usd_uzs' => '13000'])
            ->assertSessionHasNoErrors();

        $this->assertSame('13000.00', $this->currentRate());
    }

    public function test_branch_limit_blocks_new_and_reopened_branches(): void
    {
        $this->subscribe('2026-12-01');

        $this->actingAs($this->store->owner)
            ->post(route('branches.store'), ['name' => 'Yunusobod'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Branch::query()->where('tenant_id', $this->store->tenant->id)->count());

        // Limit oshirilgach qo'shiladi.
        $this->plan->update(['max_branches' => 2]);
        $this->actingAs($this->store->owner)
            ->post(route('branches.store'), ['name' => 'Yunusobod'])
            ->assertSessionHasNoErrors();

        $second = Branch::query()->where('tenant_id', $this->store->tenant->id)->where('name', 'Yunusobod')->firstOrFail();
        $second->update(['is_active' => false]);
        $this->plan->update(['max_branches' => 1]);

        $this->actingAs($this->store->owner)
            ->patch(route('branches.update', $second), ['name' => 'Yunusobod', 'is_active' => true])
            ->assertSessionHasErrors('name');

        $this->assertFalse($second->refresh()->is_active);
    }

    public function test_seller_limit_does_not_count_the_owner_or_disabled_staff(): void
    {
        $this->subscribe('2026-12-01');
        $staff = fn (string $phone) => ['name' => 'Sardor', 'phone' => $phone, 'password' => 'seller-123', 'branch_ids' => [$this->store->branch->id]];

        $this->actingAs($this->store->owner)->post(route('staff.store'), $staff('+998 93 555 66 01'))->assertSessionHasNoErrors();
        $this->actingAs($this->store->owner)->post(route('staff.store'), $staff('+998 93 555 66 02'))->assertSessionHasErrors('name');

        $first = User::query()->where('phone', '998935556601')->firstOrFail();
        $first->update(['is_active' => false]);

        $this->actingAs($this->store->owner)->post(route('staff.store'), $staff('+998 93 555 66 02'))->assertSessionHasNoErrors();

        // O'chirilganini qayta yoqish limitdan oshiradi.
        $this->actingAs($this->store->owner)
            ->patch(route('staff.update', $first), [...$staff('+998 93 555 66 01'), 'password' => '', 'is_active' => true])
            ->assertSessionHasErrors('name');
    }

    public function test_store_without_a_plan_has_no_limits(): void
    {
        $this->actingAs($this->store->owner)
            ->post(route('branches.store'), ['name' => 'Yunusobod'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Branch::query()->where('tenant_id', $this->store->tenant->id)->count());
    }

    public function test_payment_extends_from_the_current_period_or_from_today(): void
    {
        $subscription = app(Subscription::class);
        $admin = User::factory()->superadmin()->create();

        // Muddat qo'yilmagan do'kon: bugundan.
        $first = $subscription->recordPayment($this->tenant(), $this->plan, 1, '200000', null, $admin);
        $this->assertSame('2026-11-05', $first->period_from->toDateString());
        $this->assertSame('2026-12-04', $first->period_to->toDateString());

        // Muddat tugamagan: ustiga qo'shiladi.
        $second = $subscription->recordPayment($this->tenant(), $this->plan, 3, '600000', 'chegirma bilan', $admin);
        $this->assertSame('2026-12-05', $second->period_from->toDateString());
        $this->assertSame('2027-03-04', $second->period_to->toDateString());
        $this->assertSame('2027-03-04', $this->tenant()->paid_until?->toDateString());
        $this->assertSame($this->plan->id, $this->tenant()->plan_id);

        // Muddat o'tib ketgan: o'tgan kunlar uchun pul olinmaydi, bugundan boshlanadi.
        $this->travelTo('2027-06-10 10:00:00');
        $third = $subscription->recordPayment($this->tenant(), $this->plan, 1, '200000', null, $admin);
        $this->assertSame('2027-06-10', $third->period_from->toDateString());
        $this->assertSame('2027-07-09', $third->period_to->toDateString());
    }

    public function test_superadmin_records_a_payment_and_reopens_an_expired_store(): void
    {
        $this->subscribe('2026-10-01');
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->get(route('admin.tenants.show', $this->store->tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tenant.state', 'expired')
                ->where('tenant.branches', 1)
                ->where('tenant.max_branches', 1));

        $this->actingAs($admin)
            ->post(route('admin.tenants.payments', $this->store->tenant), ['plan_id' => $this->plan->id, 'months' => 2, 'amount' => '400000'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2027-01-04', $this->tenant()->paid_until?->toDateString());
        $this->assertSame('400000.00', SubscriptionPayment::query()->firstOrFail()->amount);

        $this->actingAs($this->store->owner)
            ->put(route('store-settings.rate'), ['usd_uzs' => '13000'])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('admin.tenants.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.tenants', 1)
                ->where('stats.expired', 0)
                ->where('stats.income_month', "400 000 so'm")
                ->where('tenants.0.plan', 'Kichik')
                ->where('tenants.0.state', 'active'));
    }

    public function test_superadmin_can_grant_a_trial_and_edit_plans(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->put(route('admin.tenants.subscription', $this->store->tenant), ['plan_id' => $this->plan->id, 'paid_until' => '2026-11-20'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-11-20', $this->tenant()->paid_until?->toDateString());

        $this->actingAs($admin)
            ->put(route('admin.plans.update', $this->plan), ['name' => 'Kichik', 'price' => '250000', 'max_branches' => '', 'max_users' => 5])
            ->assertSessionHasNoErrors();

        $this->plan->refresh();
        $this->assertSame('250000.00', $this->plan->price);
        $this->assertNull($this->plan->max_branches);
        $this->assertSame(5, $this->plan->max_users);

        $this->actingAs($admin)->get(route('admin.plans.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('plans', 4));
    }

    public function test_store_users_cannot_touch_billing(): void
    {
        $owner = $this->store->owner;

        $this->actingAs($owner)->get(route('admin.plans.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.tenants.show', $this->store->tenant))->assertForbidden();
        $this->actingAs($owner)
            ->post(route('admin.tenants.payments', $this->store->tenant), ['plan_id' => $this->plan->id, 'months' => 12, 'amount' => '0'])
            ->assertForbidden();
        $this->actingAs($owner)
            ->put(route('admin.tenants.subscription', $this->store->tenant), ['paid_until' => '2030-01-01'])
            ->assertForbidden();

        $this->assertNull($this->tenant()->paid_until);
    }
}
