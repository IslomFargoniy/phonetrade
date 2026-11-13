<?php

namespace Tests\Support;

use App\Actions\Stock\ReceiveStock;
use App\Actions\Tenants\CreateTenant;
use App\Enums\CashAccountType;
use App\Enums\Role;
use App\Enums\Tracking;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Category;
use App\Models\CurrencyRate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockUnit;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

/**
 * Testlar uchun tayyor do'kon: egasi, filial, kassalar, kurs va ikkita mahsulot.
 * Yaratilgandan keyin do'kon konteksti o'rnatilgan holda qoladi.
 */
class StoreFixture
{
    public Tenant $tenant;

    public User $owner;

    public Branch $branch;

    public Product $phone;

    public Product $case;

    private static int $counter = 0;

    public static function create(string $rate = '12750'): self
    {
        $fixture = new self;
        $n = ++self::$counter;

        $fixture->tenant = app(CreateTenant::class)->handle(
            name: "Do'kon {$n}",
            branchName: 'Chilonzor',
            ownerName: 'Egasi',
            ownerPhone: sprintf('99890%07d', $n),
            ownerPassword: 'password',
        );

        app(TenantContext::class)->set($fixture->tenant);

        $fixture->owner = User::query()->where('role', Role::Owner)->firstOrFail();
        $fixture->branch = Branch::query()->firstOrFail();

        CurrencyRate::query()->create([
            'date' => now()->toDateString(),
            'usd_uzs' => $rate,
            'source' => CurrencyRate::SOURCE_MANUAL,
        ]);

        $phones = Category::query()->create(['name' => 'Telefon', 'tracking' => Tracking::Serial]);
        $accessories = Category::query()->create(['name' => 'Aksesuar', 'tracking' => Tracking::Quantity]);

        $fixture->phone = Product::query()->create([
            'category_id' => $phones->id,
            'name' => 'Samsung Galaxy A55',
            'default_warranty_months' => 12,
            'default_max_discount_pct' => '15',
        ]);
        $fixture->case = Product::query()->create([
            'category_id' => $accessories->id,
            'name' => 'Chexol Galaxy A55',
        ]);

        return $fixture;
    }

    public function cashAccount(CashAccountType $type = CashAccountType::Cash, ?Branch $branch = null): CashAccount
    {
        return CashAccount::query()
            ->where('branch_id', ($branch ?? $this->branch)->id)
            ->where('type', $type)
            ->firstOrFail();
    }

    /**
     * Omborga bitta telefon kiritadi.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function receivePhone(int $n = 1, array $overrides = [], string $currency = 'USD'): StockUnit
    {
        app(ReceiveStock::class)->handle($this->branch, $this->owner, ['type' => 'opening', 'currency' => $currency, 'lines' => [[
            'product_id' => $this->phone->id,
            'unit_cost' => '320',
            'sale_price' => '4850000',
            'sale_currency' => 'UZS',
            'max_discount_pct' => '15',
            'warranty_months' => 12,
            'units' => [['imei1' => self::imei($n)]],
            ...$overrides,
        ]]]);

        return StockUnit::query()->latest('id')->firstOrFail();
    }

    /**
     * Omborga aksesuar partiyasini kiritadi va variantni qaytaradi.
     */
    public function receiveCases(int $qty, string $unitCost = '20000', string $salePrice = '60000'): ProductVariant
    {
        app(ReceiveStock::class)->handle($this->branch, $this->owner, ['type' => 'opening', 'currency' => 'UZS', 'lines' => [[
            'product_id' => $this->case->id,
            'barcode' => '4780012345678',
            'unit_cost' => $unitCost,
            'sale_price' => $salePrice,
            'sale_currency' => 'UZS',
            'qty' => $qty,
        ]]]);

        return ProductVariant::query()->where('barcode', '4780012345678')->firstOrFail();
    }

    public function setRate(string $rate): void
    {
        CurrencyRate::query()->whereDate('date', now()->toDateString())->firstOrFail()->update(['usd_uzs' => $rate]);
    }

    public function seller(): User
    {
        $seller = User::factory()->seller()->create(['tenant_id' => $this->tenant->id]);
        $seller->branches()->attach($this->branch);

        return $seller;
    }

    /**
     * Luhn bo'yicha to'g'ri IMEI yasaydi (testlarda takrorlanmasligi uchun tartib raqami bilan).
     */
    public static function imei(int $n): string
    {
        $body = sprintf('35291411%06d', $n);
        $sum = 0;

        foreach (str_split(strrev($body)) as $position => $char) {
            $digit = (int) $char;

            if ($position % 2 === 0) {
                $digit *= 2;
                $digit = $digit > 9 ? $digit - 9 : $digit;
            }

            $sum += $digit;
        }

        return $body.((10 - $sum % 10) % 10);
    }
}
