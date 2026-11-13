<?php

namespace Tests\Feature\Sales;

use App\Actions\Sales\CloseSale;
use App\Enums\SaleStatus;
use App\Enums\StockStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class SaleHttpTest extends TestCase
{
    use RefreshDatabase;

    private function leaveContext(): void
    {
        app(TenantContext::class)->forget();
    }

    public function test_scanner_lookup_finds_a_phone_and_never_exposes_cost(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1);
        $this->leaveContext();

        foreach ([$phone->imei1, $phone->barcode] as $code) {
            $response = $this->actingAs($store->owner)->getJson(route('pos.lookup', ['code' => $code]));

            $response->assertOk()->assertJsonPath('exact', true)->assertJsonPath('units.0.id', $phone->id);
            $this->assertArrayNotHasKey('cost', $response->json('units.0'));
        }

        $this->actingAs($store->owner)->getJson(route('pos.lookup', ['code' => 'Galaxy']))
            ->assertJsonPath('exact', false)
            ->assertJsonCount(1, 'units');
    }

    public function test_sold_phone_is_not_offered_again(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1);
        app(CloseSale::class)->handle($store->branch, $store->owner, [['stock_unit_id' => $phone->id]], [
            ['method' => 'cash', 'currency' => 'UZS', 'amount' => '4850000'],
        ]);
        $this->leaveContext();

        $this->actingAs($store->owner)->getJson(route('pos.lookup', ['code' => $phone->imei1]))
            ->assertJsonCount(0, 'units');
    }

    public function test_preview_returns_the_same_total_the_sale_will_store(): void
    {
        $store = StoreFixture::create();
        $iphone = $store->receivePhone(1, ['sale_price' => '460', 'sale_currency' => 'USD', 'unit_cost' => '400']);
        $case = $store->receiveCases(18);
        $this->leaveContext();

        $this->actingAs($store->owner)->postJson(route('pos.preview'), ['items' => [
            ['stock_unit_id' => $iphone->id],
            ['variant_id' => $case->id, 'qty' => 1],
        ]])
            ->assertOk()
            ->assertJsonPath('currency', 'UZS')
            ->assertJsonPath('mixed', true)
            ->assertJsonPath('total', '5925000.00');
    }

    public function test_preview_reports_a_discount_above_the_limit(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1);
        $this->leaveContext();

        $this->actingAs($store->owner)->postJson(route('pos.preview'), ['items' => [
            ['stock_unit_id' => $phone->id, 'discount' => '900000'],
        ]])
            ->assertStatus(422)
            ->assertJsonPath('code', 'DISCOUNT_LIMIT_EXCEEDED');
    }

    public function test_seller_closes_a_sale_and_sees_no_profit(): void
    {
        $store = StoreFixture::create();
        $seller = $store->seller();
        $phone = $store->receivePhone(1);
        $this->leaveContext();

        $response = $this->actingAs($seller)->post(route('sales.store'), [
            'items' => [['stock_unit_id' => $phone->id, 'discount' => '150000']],
            'payments' => [
                ['method' => 'cash', 'currency' => 'UZS', 'amount' => '4700000'],
                ['method' => 'card', 'currency' => 'UZS', 'amount' => ''],
            ],
            'customer_phone' => '+998 90 123 45 67',
            'customer_name' => 'Aliyev Jasur',
        ]);

        $sale = Sale::query()->firstOrFail();

        $response->assertSessionHasNoErrors()->assertRedirect(route('sales.show', ['sale' => $sale, 'print' => 1]));
        $this->assertSame($seller->id, $sale->user_id);
        $this->assertSame('Aliyev Jasur', Customer::query()->findOrFail($sale->customer_id)->full_name);

        $this->actingAs($seller)->get(route('sales.show', $sale))
            ->assertInertia(fn (Assert $page) => $page
                ->component('sales/show')
                ->where('canRefund', false)
                ->missing('sale.profit_label')
                ->missing('sale.items.0.cost_label')
                ->missing('sale.items.0.profit_label')
                ->where('sale.total_label', "4 700 000 so'm"));

        $this->actingAs($store->owner)->get(route('sales.show', $sale))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sale.profit_label', "620 000 so'm")
                ->where('sale.items.0.cost_label', "4 080 000 so'm"));
    }

    public function test_underpaid_sale_comes_back_as_a_form_error(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1);
        $this->leaveContext();

        $this->actingAs($store->owner)->from(route('pos.index'))->post(route('sales.store'), [
            'items' => [['stock_unit_id' => $phone->id]],
            'payments' => [['method' => 'cash', 'currency' => 'UZS', 'amount' => '1000000']],
        ])->assertRedirect(route('pos.index'))->assertSessionHasErrors('payments');

        $this->assertSame(0, Sale::query()->count());
    }

    public function test_refund_needs_permission(): void
    {
        $store = StoreFixture::create();
        $seller = $store->seller();
        $phone = $store->receivePhone(1);
        $sale = app(CloseSale::class)->handle($store->branch, $store->owner, [['stock_unit_id' => $phone->id]], [
            ['method' => 'cash', 'currency' => 'UZS', 'amount' => '4850000'],
        ]);
        $item = $sale->items()->firstOrFail();
        $this->leaveContext();

        $payload = ['items' => [['sale_item_id' => $item->id, 'qty' => 1]], 'refund_from' => 'cash', 'reason' => 'Nuqson'];

        $this->actingAs($seller)->post(route('sales.refund', $sale), $payload)->assertForbidden();

        $seller->update(['can_refund' => true]);

        $this->actingAs($seller)->post(route('sales.refund', $sale), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sales.show', $sale));

        $this->assertSame(SaleStatus::Returned, $sale->fresh()->status);
        $this->assertSame(StockStatus::Returned, $phone->fresh()->status);

        // Qaytgan telefonni faqat egasi yana sotuvga chiqaradi.
        $this->actingAs($seller)->post(route('stock.units.restock', $phone))->assertForbidden();
        $this->actingAs($store->owner)->post(route('stock.units.restock', $phone))->assertSessionHasNoErrors();
        $this->assertSame(StockStatus::InStock, $phone->fresh()->status);
    }

    public function test_sales_are_found_by_imei_number_and_customer_phone(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1);
        $other = $store->receivePhone(2);
        $customer = Customer::query()->create(['full_name' => 'Aliyev Jasur', 'phone' => '998901234567']);
        $pay = [['method' => 'cash', 'currency' => 'UZS', 'amount' => '4850000']];
        $sale = app(CloseSale::class)->handle($store->branch, $store->owner, [['stock_unit_id' => $phone->id]], $pay, $customer->id);
        app(CloseSale::class)->handle($store->branch, $store->owner, [['stock_unit_id' => $other->id]], $pay);
        $this->leaveContext();

        foreach ([$phone->imei1, (string) $sale->number, '+998 90 123 45 67'] as $query) {
            $this->actingAs($store->owner)->get(route('sales.index', ['q' => $query]))
                ->assertInertia(fn (Assert $page) => $page->has('sales', 1)->where('sales.0.id', $sale->id));
        }

        $this->actingAs($store->owner)->get(route('sales.index'))
            ->assertInertia(fn (Assert $page) => $page->has('sales', 2));
    }

    public function test_another_stores_sale_is_not_reachable(): void
    {
        $store = StoreFixture::create();
        $phone = $store->receivePhone(1);
        $sale = app(CloseSale::class)->handle($store->branch, $store->owner, [['stock_unit_id' => $phone->id]], [
            ['method' => 'cash', 'currency' => 'UZS', 'amount' => '4850000'],
        ]);
        $stranger = StoreFixture::create()->owner;
        $this->leaveContext();

        $this->actingAs($stranger)->get(route('sales.show', $sale))->assertNotFound();
        $this->actingAs($stranger)->get(route('pos.index'))->assertOk();
    }
}
