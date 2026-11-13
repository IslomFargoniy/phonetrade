<?php

namespace Database\Seeders;

use App\Actions\Stock\ReceiveStock;
use App\Actions\Tenants\CreateTenant;
use App\Enums\AttributeType;
use App\Enums\Role;
use App\Enums\Tracking;
use App\Models\Attribute;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CurrencyRate;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Faqat lokal ishlab chiqish uchun namuna ma'lumot. Productionda ishlatilmaydi:
 * u yerda superadmin `superadmin:create` buyrug'i bilan yaratiladi.
 *
 * Model hodisalari o'chirilmaydi: tenant_id aynan `creating` hodisasida to'ldiriladi.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->create([
            'name' => 'Superadmin',
            'phone' => '998900000000',
            'password' => 'password',
            'role' => Role::Superadmin,
        ]);

        $tenant = app(CreateTenant::class)->handle(
            name: 'Namuna do\'kon',
            branchName: 'Chilonzor filiali',
            ownerName: 'Namuna Egasi',
            ownerPhone: '998901111111',
            ownerPassword: 'password',
        );

        app(TenantContext::class)->runFor($tenant, fn () => $this->seedCatalog());
    }

    private function seedCatalog(): void
    {
        CurrencyRate::query()->create(['date' => now()->toDateString(), 'usd_uzs' => '12750', 'source' => CurrencyRate::SOURCE_MANUAL]);

        $phones = Category::query()->create(['name' => 'Telefon', 'tracking' => Tracking::Serial]);
        $accessories = Category::query()->create(['name' => 'Aksesuar', 'tracking' => Tracking::Quantity]);

        $attributes = [
            'Xotira' => [[$phones], ['128 GB', '8/128 GB', '8/256 GB']],
            'Rang' => [[$phones, $accessories], ['Qora', 'Oq', "Ko'k"]],
            'Holat' => [[$phones], ['Yangi', 'Ishlatilgan A', 'Ishlatilgan B']],
        ];

        foreach (array_keys($attributes) as $sort => $name) {
            [$categories, $options] = $attributes[$name];

            $attribute = Attribute::query()->create(['name' => $name, 'type' => AttributeType::Select, 'sort' => $sort]);
            $attribute->categories()->sync(array_map(fn (Category $category) => $category->id, $categories));

            foreach ($options as $index => $value) {
                $attribute->options()->create(['value' => $value, 'sort' => $index]);
            }
        }

        $battery = Attribute::query()->create(['name' => 'Batareya', 'type' => AttributeType::Number, 'sort' => 3]);
        $battery->categories()->sync([$phones->id]);

        foreach ([['Samsung Galaxy A55', 12, '15'], ['iPhone 13', 3, '10']] as [$name, $warranty, $discount]) {
            Product::query()->create([
                'category_id' => $phones->id,
                'name' => $name,
                'default_warranty_months' => $warranty,
                'default_max_discount_pct' => $discount,
            ]);
        }

        Product::query()->create(['category_id' => $accessories->id, 'name' => 'Chexol Galaxy A55, silikon']);
        $supplier = Supplier::query()->create(['name' => 'Malika bozori — Rustam aka']);

        $this->seedStock($supplier);
    }

    /**
     * Dizayndagi namunalar: 3 ta Galaxy A55, bitta ishlatilgan iPhone 13 va chexollar.
     */
    private function seedStock(Supplier $supplier): void
    {
        $branch = Branch::query()->firstOrFail();
        $owner = User::query()->where('role', Role::Owner)->firstOrFail();
        $product = fn (string $name) => Product::query()->where('name', $name)->firstOrFail()->id;
        $receive = app(ReceiveStock::class);

        $receive->handle($branch, $owner, ['type' => 'supplier', 'currency' => 'USD', 'supplier_id' => $supplier->id, 'lines' => [[
            'product_id' => $product('Samsung Galaxy A55'),
            'attributes' => ['Xotira' => '8/256 GB', 'Rang' => 'Qora', 'Holat' => 'Yangi'],
            'unit_cost' => '320', 'sale_price' => '4850000', 'sale_currency' => 'UZS',
            'max_discount_pct' => '15', 'warranty_months' => 12,
            'units' => [
                ['imei1' => '352914110000011', 'imei2' => '352914110000029'],
                ['imei1' => '352914110000037'],
                ['imei1' => '352914110000045'],
            ],
        ]]]);

        $receive->handle($branch, $owner, ['type' => 'opening', 'currency' => 'USD', 'lines' => [[
            'product_id' => $product('iPhone 13'),
            'attributes' => ['Xotira' => '128 GB', 'Rang' => 'Oq', 'Holat' => 'Ishlatilgan A', 'Batareya' => '88'],
            'unit_cost' => '400', 'sale_price' => '460', 'sale_currency' => 'USD',
            'max_discount_pct' => '10', 'warranty_months' => 3,
            'units' => [['imei1' => '356728090000015']],
        ]]]);

        $receive->handle($branch, $owner, ['type' => 'opening', 'currency' => 'UZS', 'lines' => [[
            'product_id' => $product('Chexol Galaxy A55, silikon'),
            'attributes' => ['Rang' => 'Qora'],
            'barcode' => '4780012345678',
            'unit_cost' => '35000', 'sale_price' => '60000', 'sale_currency' => 'UZS',
            'qty' => 18,
        ]]]);
    }
}
