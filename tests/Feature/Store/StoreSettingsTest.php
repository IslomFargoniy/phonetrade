<?php

namespace Tests\Feature\Store;

use App\Actions\Branches\CreateBranch;
use App\Models\Branch;
use App\Models\CurrencyRate;
use App\Models\InstallmentRate;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function branchFor(User $owner): Branch
    {
        return app(TenantContext::class)->runFor(
            $owner->tenant_id,
            fn () => app(CreateBranch::class)->handle(['name' => 'Chilonzor']),
        );
    }

    public function test_settings_page_is_displayed_for_the_owner_only(): void
    {
        $owner = User::factory()->create();
        $this->branchFor($owner);

        $this->actingAs($owner)->get(route('store-settings.edit'))->assertOk();
        $this->actingAs(User::factory()->seller()->create())->get(route('store-settings.edit'))->assertForbidden();
    }

    public function test_owner_sets_todays_rate(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->put(route('store-settings.rate'), ['usd_uzs' => '12750'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->put(route('store-settings.rate'), ['usd_uzs' => '12800'])->assertSessionHasNoErrors();

        $rates = CurrencyRate::query()->where('tenant_id', $owner->tenant_id)->get();

        $this->assertCount(1, $rates);
        $this->assertSame('12800.00', $rates->first()->usd_uzs);
        $this->assertSame(CurrencyRate::SOURCE_MANUAL, $rates->first()->source);
    }

    public function test_rate_must_be_positive(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('store-settings.rate'), ['usd_uzs' => '0'])
            ->assertSessionHasErrors('usd_uzs');
    }

    public function test_owner_updates_installment_markups(): void
    {
        $owner = User::factory()->create();
        $branch = $this->branchFor($owner);

        $this->actingAs($owner)
            ->put(route('store-settings.installment-rates', $branch), ['rates' => [
                ['months' => 3, 'markup_pct' => '18'],
                ['months' => 6, 'markup_pct' => '30'],
                ['months' => 9, 'markup_pct' => '42.5'],
                ['months' => 12, 'markup_pct' => '50'],
            ]])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            [3 => '18.00', 6 => '30.00', 9 => '42.50', 12 => '50.00'],
            InstallmentRate::query()->where('branch_id', $branch->id)->orderBy('months')->pluck('markup_pct', 'months')->all(),
        );
    }

    public function test_only_the_four_fixed_terms_are_accepted(): void
    {
        $owner = User::factory()->create();
        $branch = $this->branchFor($owner);

        $this->actingAs($owner)
            ->put(route('store-settings.installment-rates', $branch), ['rates' => [
                ['months' => 3, 'markup_pct' => '18'],
                ['months' => 6, 'markup_pct' => '30'],
                ['months' => 9, 'markup_pct' => '42'],
                ['months' => 24, 'markup_pct' => '90'],
            ]])
            ->assertSessionHasErrors('rates.3.months');
    }

    public function test_owner_updates_rounding_and_receipt_details(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->put(route('store-settings.general'), [
                'round_step_uzs' => 5000,
                'round_step_usd' => 5,
                'receipt_name' => 'Smart Mobile',
                'receipt_phone' => '+998 71 200 00 00',
                'receipt_footer' => 'Xaridingiz uchun rahmat',
            ])
            ->assertSessionHasNoErrors();

        $tenant = Tenant::query()->findOrFail($owner->tenant_id);

        $this->assertSame('5000', $tenant->setting('round_step_uzs'));
        $this->assertSame('Smart Mobile', $tenant->setting('receipt.name'));
    }

    public function test_cbu_rate_is_stored_for_stores_without_todays_rate(): void
    {
        Http::fake(['cbu.uz/*' => Http::response([['Ccy' => 'USD', 'Rate' => '12745.11']])]);

        $withManual = Tenant::factory()->create();
        $withoutRate = Tenant::factory()->create();
        $blocked = Tenant::factory()->blocked()->create();
        CurrencyRate::factory()->create(['tenant_id' => $withManual->id, 'usd_uzs' => '12800.00']);

        $this->artisan('rates:fetch')->assertSuccessful();

        $this->assertSame('12800.00', CurrencyRate::query()->where('tenant_id', $withManual->id)->value('usd_uzs'));
        $this->assertSame('12745.11', CurrencyRate::query()->where('tenant_id', $withoutRate->id)->value('usd_uzs'));
        $this->assertFalse(CurrencyRate::query()->where('tenant_id', $blocked->id)->exists());
    }

    public function test_rate_fetch_fails_cleanly_when_the_bank_is_unreachable(): void
    {
        Http::fake(['cbu.uz/*' => Http::response(null, 500)]);
        Tenant::factory()->create();

        $this->artisan('rates:fetch')->assertFailed();

        $this->assertSame(0, CurrencyRate::query()->count());
    }
}
