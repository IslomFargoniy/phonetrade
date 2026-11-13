<?php

namespace Tests\Feature\Stock;

use App\Actions\Branches\CreateBranch;
use App\Actions\Stock\ReceiveStock;
use App\Enums\StockStatus;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockTransfer;
use App\Models\StockUnit;
use App\Services\Cash\CashService;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class StockHttpTest extends TestCase
{
    use RefreshDatabase;

    private function receivePhone(StoreFixture $store, int $n = 1): StockUnit
    {
        app(ReceiveStock::class)->handle($store->branch, $store->owner, ['type' => 'opening', 'currency' => 'USD', 'lines' => [[
            'product_id' => $store->phone->id, 'unit_cost' => '320', 'sale_price' => '4850000', 'sale_currency' => 'UZS',
            'units' => [['imei1' => StoreFixture::imei($n)]],
        ]]]);

        return StockUnit::query()->latest('id')->firstOrFail();
    }

    /**
     * So'rov davomida kontekst middleware tomonidan o'rnatiladi; test tayyorlash bosqichidagi
     * kontekst so'rovga aralashmasligi uchun tozalanadi.
     */
    private function leaveContext(): void
    {
        app(TenantContext::class)->forget();
    }

    public function test_owner_receives_phones_through_the_form(): void
    {
        $store = StoreFixture::create();
        $this->leaveContext();

        $response = $this->actingAs($store->owner)->post(route('receipts.store'), [
            'type' => 'opening',
            'currency' => 'USD',
            'lines' => [[
                'product_id' => $store->phone->id,
                'attributes' => ['Rang' => 'Qora', 'Xotira' => ''],
                'unit_cost' => '320',
                'sale_price' => '4850000',
                'sale_currency' => 'UZS',
                'max_discount_pct' => '15',
                'warranty_months' => '12',
                'units' => [['imei1' => StoreFixture::imei(1), 'imei2' => ''], ['imei1' => StoreFixture::imei(2)]],
            ]],
        ]);

        $purchase = Purchase::query()->firstOrFail();

        $response->assertSessionHasNoErrors()->assertRedirect(route('receipts.show', $purchase));
        $this->assertSame(2, StockUnit::query()->count());
        $this->assertSame(['Rang' => 'Qora'], StockUnit::query()->firstOrFail()->attributes);

        $this->actingAs($store->owner)->get(route('receipts.show', $purchase))->assertOk();
    }

    public function test_invalid_imei_comes_back_as_a_form_error(): void
    {
        $store = StoreFixture::create();
        $this->leaveContext();

        $this->actingAs($store->owner)
            ->from(route('receipts.create'))
            ->post(route('receipts.store'), [
                'type' => 'opening', 'currency' => 'USD',
                'lines' => [[
                    'product_id' => $store->phone->id, 'unit_cost' => '320', 'sale_price' => '4850000', 'sale_currency' => 'UZS',
                    'units' => [['imei1' => '123456789012345']],
                ]],
            ])
            ->assertRedirect(route('receipts.create'))
            ->assertSessionHasErrors('lines');
    }

    public function test_seller_can_only_buy_from_a_customer(): void
    {
        $store = StoreFixture::create();
        $seller = $store->seller();
        $account = $store->cashAccount();
        app(CashService::class)->deposit($account, Money::uzs(1000000), 'sale');
        $this->leaveContext();

        $line = [
            'product_id' => $store->phone->id, 'unit_cost' => '500000', 'sale_price' => '900000', 'sale_currency' => 'UZS',
            'units' => [['imei1' => StoreFixture::imei(1)]],
        ];

        $this->actingAs($seller)
            ->post(route('receipts.store'), ['type' => 'opening', 'currency' => 'UZS', 'lines' => [$line]])
            ->assertForbidden();

        $this->actingAs($seller)
            ->post(route('receipts.store'), [
                'type' => 'trade_in', 'currency' => 'UZS',
                'customer_phone' => '+998 90 123 45 67', 'customer_name' => 'Aliyev Jasur',
                'cash_account_id' => $account->id,
                'lines' => [$line],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Aliyev Jasur', Customer::query()->where('phone', '998901234567')->value('full_name'));
        $this->assertSame(1, StockUnit::query()->count());
    }

    public function test_trade_in_cannot_be_paid_from_another_stores_cash_account(): void
    {
        $store = StoreFixture::create();
        $other = StoreFixture::create();
        $foreignAccount = $other->cashAccount();
        $this->leaveContext();

        $this->actingAs($store->owner)
            ->post(route('receipts.store'), [
                'type' => 'trade_in', 'currency' => 'UZS',
                'customer_phone' => '90 123 45 67',
                'cash_account_id' => $foreignAccount->id,
                'lines' => [[
                    'product_id' => $store->phone->id, 'unit_cost' => '500000', 'sale_price' => '900000', 'sale_currency' => 'UZS',
                    'units' => [['imei1' => StoreFixture::imei(1)]],
                ]],
            ])
            ->assertSessionHasErrors('cash_account_id');
    }

    public function test_seller_never_receives_cost_fields(): void
    {
        $store = StoreFixture::create();
        $seller = $store->seller();
        $this->receivePhone($store);
        $this->leaveContext();

        $this->actingAs($seller)->get(route('stock.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('stock/index')
                ->where('canSeeCost', false)
                ->has('units', 1, fn (Assert $unit) => $unit
                    ->missing('cost')
                    ->missing('cost_label')
                    ->missing('cost_rate')
                    ->missing('cost_currency')
                    ->where('sale_price', '4850000.00')
                    ->etc()));

        $this->actingAs($store->owner)->get(route('stock.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canSeeCost', true)
                ->has('units', 1, fn (Assert $unit) => $unit->where('cost', '320.00')->etc()));
    }

    public function test_seller_sees_only_the_current_branch(): void
    {
        $store = StoreFixture::create();
        $seller = $store->seller();
        $otherBranch = app(CreateBranch::class)->handle(['name' => 'Yunusobod']);
        $this->receivePhone($store, 1);
        StockUnit::query()->firstOrFail()->update(['branch_id' => $otherBranch->id]);
        $this->leaveContext();

        $this->actingAs($seller)->get(route('stock.index', ['scope' => 'all']))
            ->assertInertia(fn (Assert $page) => $page->has('units', 0));

        $this->actingAs($store->owner)->get(route('stock.index', ['scope' => 'all']))
            ->assertInertia(fn (Assert $page) => $page->has('units', 1));
    }

    public function test_stock_can_be_searched_by_imei_and_barcode(): void
    {
        $store = StoreFixture::create();
        $first = $this->receivePhone($store, 1);
        $this->receivePhone($store, 2);
        $this->leaveContext();

        foreach ([$first->imei1, $first->barcode] as $query) {
            $this->actingAs($store->owner)->get(route('stock.index', ['q' => $query]))
                ->assertInertia(fn (Assert $page) => $page->has('units', 1)->where('units.0.id', $first->id));
        }
    }

    public function test_only_the_owner_changes_prices(): void
    {
        $store = StoreFixture::create();
        $seller = $store->seller();
        $unit = $this->receivePhone($store);
        $this->leaveContext();

        $payload = ['sale_price' => '4700000', 'sale_currency' => 'UZS', 'max_discount_pct' => '10', 'warranty_months' => '6'];

        $this->actingAs($seller)->patch(route('stock.units.update', $unit), $payload)->assertForbidden();
        $this->actingAs($store->owner)->patch(route('stock.units.update', $unit), $payload)->assertSessionHasNoErrors();

        $this->assertSame('4700000.00', $unit->fresh()->sale_price);
        $this->assertSame('10.00', $unit->fresh()->max_discount_pct);
    }

    public function test_transfer_is_sent_and_received_over_http(): void
    {
        $store = StoreFixture::create();
        $otherBranch = app(CreateBranch::class)->handle(['name' => 'Yunusobod']);
        $seller = $store->seller();
        $unit = $this->receivePhone($store);
        $this->leaveContext();

        $this->actingAs($store->owner)
            ->post(route('transfers.store'), ['to_branch_id' => $otherBranch->id, 'unit_ids' => [$unit->id]])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('transfers.index'));

        $transfer = StockTransfer::query()->firstOrFail();
        $this->assertSame(StockStatus::InTransit, $unit->fresh()->status);

        // Sotuvchi qabul qiluvchi filialga biriktirilmagan — tasdiqlay olmaydi.
        $this->actingAs($seller)->post(route('transfers.receive', $transfer))->assertForbidden();

        $this->actingAs($store->owner)->post(route('transfers.receive', $transfer))->assertRedirect(route('transfers.index'));
        $this->assertSame($otherBranch->id, $unit->fresh()->branch_id);

        $this->actingAs($store->owner)->get(route('transfers.index'))->assertOk();
    }

    public function test_catalog_is_managed_by_the_owner(): void
    {
        $store = StoreFixture::create();
        $seller = $store->seller();
        $category = Category::query()->where('name', 'Telefon')->firstOrFail();
        $this->leaveContext();

        $this->actingAs($seller)->get(route('catalog.index'))->assertForbidden();
        $this->actingAs($store->owner)->get(route('catalog.index'))->assertOk();

        $this->actingAs($store->owner)->post(route('catalog.attributes.store'), [
            'name' => 'Rang', 'type' => 'select', 'options' => "Qora, Oq,\nKo'k, Qora", 'category_ids' => [$category->id],
        ])->assertSessionHasNoErrors();

        $attribute = Attribute::query()->where('name', 'Rang')->firstOrFail();
        $this->assertSame(['Qora', 'Oq', "Ko'k"], $attribute->options()->orderBy('sort')->pluck('value')->all());

        $this->actingAs($store->owner)->patch(route('catalog.attributes.update', $attribute), [
            'name' => 'Rang', 'type' => 'select', 'options' => 'Oq, Yashil', 'category_ids' => [$category->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Oq', 'Yashil'], $attribute->options()->orderBy('sort')->pluck('value')->all());

        $this->actingAs($store->owner)->post(route('catalog.products.store'), [
            'category_id' => $category->id, 'name' => 'iPhone 13', 'default_warranty_months' => 3, 'default_max_discount_pct' => 10,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Product::query()->where('name', 'iPhone 13')->exists());

        $this->actingAs($store->owner)->get(route('receipts.create'))->assertOk();
    }
}
