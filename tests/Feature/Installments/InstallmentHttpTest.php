<?php

namespace Tests\Feature\Installments;

use App\Http\Controllers\Customers\CustomerController;
use App\Models\Customer;
use App\Models\CustomerFile;
use App\Models\InstallmentPlan;
use App\Models\Sale;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class InstallmentHttpTest extends TestCase
{
    use RefreshDatabase;

    private StoreFixture $store;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(CustomerController::DISK);
        $this->travelTo('2026-11-05 14:00:00');
        $this->store = StoreFixture::create();
        app(TenantContext::class)->forget();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function contract(int $unitId, array $overrides = []): array
    {
        return [
            'mode' => 'installment',
            'items' => [['stock_unit_id' => $unitId]],
            'installment' => ['currency' => 'UZS', 'months' => 3, 'pay_day' => 13, 'down_payment' => '0'],
            'payments' => [['method' => 'cash', 'currency' => 'UZS', 'amount' => '200000']],
            'customer_phone' => '+998 90 123 45 67',
            'customer_name' => 'Aliyev Jasur Karimovich',
            'customer_phone2' => '93 555 66 77',
            'passport_series' => 'ab',
            'passport_number' => '1234567',
            'pinfl' => '12345678901234',
            'customer_address' => 'Chilonzor, 5-mavze, 12-uy',
            'passport_files' => [UploadedFile::fake()->image('pasport.jpg')],
            ...$overrides,
        ];
    }

    private function phone(int $n = 1): int
    {
        // So'rovdan keyin kontekst tozalanadi, shuning uchun tayyorlash bosqichida qayta o'rnatiladi.
        return app(TenantContext::class)->runFor(
            $this->store->tenant,
            fn () => $this->store->receivePhone($n, ['unit_cost' => '450000', 'sale_price' => '500000', 'max_discount_pct' => '0'], 'UZS')->id,
        );
    }

    public function test_preview_returns_the_schedule_the_contract_will_have(): void
    {
        $unitId = $this->phone();

        $this->actingAs($this->store->owner)->postJson(route('pos.preview'), [
            'mode' => 'installment',
            'items' => [['stock_unit_id' => $unitId]],
            'installment' => ['currency' => 'UZS', 'months' => 3, 'pay_day' => 13, 'down_payment' => '100000'],
        ])
            ->assertOk()
            ->assertJsonPath('installment.mode', 'B')
            ->assertJsonPath('installment.due_today', '100000.00')
            ->assertJsonPath('installment.debt_label', "480 000 so'm")
            ->assertJsonCount(4, 'installment.rows')
            ->assertJsonPath('installment.rows.1.date', '13.12.2026');
    }

    public function test_seller_opens_a_contract_with_a_full_customer_card(): void
    {
        $unitId = $this->phone();
        $seller = app(TenantContext::class)->runFor($this->store->tenant, fn () => $this->store->seller());

        $response = $this->actingAs($seller)->post(route('sales.store'), $this->contract($unitId));

        $response->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();
        $plan = InstallmentPlan::query()->firstOrFail();
        $customer = Customer::query()->firstOrFail();

        $response->assertRedirect(route('sales.show', ['sale' => $sale, 'print' => 1]));
        $this->assertSame('600000.00', $plan->total_debt);
        $this->assertSame('AB', $customer->passport_series);
        $this->assertSame('998935556677', $customer->phone2);
        $this->assertSame('12345678901234', $customer->pinfl);

        $file = CustomerFile::query()->firstOrFail();
        Storage::disk(CustomerController::DISK)->assertExists($file->path);

        // Pasport rasmi faqat tizim orqali, kirgan xodimga beriladi.
        $this->actingAs($seller)->get(route('customers.files.show', $file))->assertOk();
        $this->post(route('logout'));
        $this->get(route('customers.files.show', $file))->assertRedirect(route('login'));
    }

    public function test_contract_needs_the_customer_card_and_a_passport_photo(): void
    {
        $unitId = $this->phone();

        $this->actingAs($this->store->owner)
            ->post(route('sales.store'), $this->contract($unitId, ['pinfl' => '123', 'customer_address' => '', 'passport_series' => 'A1']))
            ->assertSessionHasErrors(['pinfl', 'customer_address', 'passport_series']);

        $this->actingAs($this->store->owner)
            ->post(route('sales.store'), $this->contract($unitId, ['passport_files' => []]))
            ->assertSessionHasErrors('passport_files');

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, InstallmentPlan::query()->count());
    }

    public function test_returning_customer_does_not_need_a_new_photo(): void
    {
        $this->actingAs($this->store->owner)->post(route('sales.store'), $this->contract($this->phone(1)))->assertSessionHasNoErrors();

        $this->actingAs($this->store->owner)
            ->post(route('sales.store'), $this->contract($this->phone(2), ['passport_files' => []]))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, InstallmentPlan::query()->count());
        $this->assertSame(1, Customer::query()->count());
    }

    public function test_payment_is_accepted_from_the_instalment_list(): void
    {
        $this->actingAs($this->store->owner)->post(route('sales.store'), $this->contract($this->phone()));
        $plan = InstallmentPlan::query()->firstOrFail();

        $this->actingAs($this->store->owner)->get(route('installments.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('installments/index')
                ->where('counts.active', 1)
                ->where('counts.late', 0)
                ->has('plans', 1)
                ->where('plans.0.remaining', '400000.00')
                ->where('plans.0.next_date', '13.12.2026')
                ->where('plans.0.state', 'upcoming'));

        $this->actingAs($this->store->owner)
            ->from(route('installments.index'))
            ->post(route('installments.pay', $plan), ['amount' => '150000', 'currency' => 'UZS', 'method' => 'card'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('installments.index'));

        $this->assertSame('350000.00', $plan->fresh()->paid_total);

        $this->actingAs($this->store->owner)
            ->from(route('installments.index'))
            ->post(route('installments.pay', $plan), ['amount' => '999999', 'currency' => 'UZS', 'method' => 'cash'])
            ->assertSessionHasErrors('amount');
    }

    public function test_overdue_contracts_are_listed_with_the_days_late(): void
    {
        $this->actingAs($this->store->owner)->post(route('sales.store'), $this->contract($this->phone()));

        // 13.12.2026 dagi oylik to'lanmagan; 8 kun o'tdi.
        $this->travelTo('2026-12-21 10:00:00');

        $this->actingAs($this->store->owner)->get(route('installments.index', ['filter' => 'late']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('counts.late', 1)
                ->has('plans', 1)
                ->where('plans.0.state', 'late')
                ->where('plans.0.state_label', '8 kun kechikkan'));

        $this->actingAs($this->store->owner)->get(route('installments.index', ['filter' => 'today']))
            ->assertInertia(fn (Assert $page) => $page->has('plans', 0));
    }

    public function test_customer_card_can_be_found_edited_and_is_isolated_per_store(): void
    {
        $this->actingAs($this->store->owner)->post(route('sales.store'), $this->contract($this->phone()));
        $customer = Customer::query()->firstOrFail();

        $this->actingAs($this->store->owner)->getJson(route('customers.lookup', ['phone' => '90 123 45 67']))
            ->assertJsonPath('customer.full_name', 'Aliyev Jasur Karimovich')
            ->assertJsonCount(1, 'customer.files');

        $this->actingAs($this->store->owner)->get(route('customers.index', ['q' => 'Aliyev', 'customer' => $customer->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('customers/index')
                ->has('customers', 1)
                ->where('selected.pinfl', '12345678901234')
                ->has('selected.plans', 1)
                ->has('selected.sales', 1));

        $this->actingAs($this->store->owner)->post(route('customers.update', $customer), [
            'full_name' => 'Aliyev Jasur', 'phone' => '+998 90 123 45 67', 'address' => 'Yunusobod',
            'passport_series' => 'AC', 'passport_number' => '7654321', 'pinfl' => '12345678901234',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Yunusobod', $customer->fresh()->address);

        $stranger = StoreFixture::create()->owner;
        app(TenantContext::class)->forget();

        $this->actingAs($stranger)->getJson(route('customers.lookup', ['phone' => '90 123 45 67']))->assertJsonPath('customer', null);
        $this->actingAs($stranger)->get(route('customers.files.show', CustomerFile::query()->firstOrFail()))->assertNotFound();
        $this->actingAs($stranger)->post(route('customers.update', $customer), ['full_name' => 'X', 'phone' => '901234567'])->assertNotFound();
    }
}
