<?php

namespace Database\Seeders;

use App\Actions\Tenants\CreateTenant;
use App\Enums\AttributeType;
use App\Enums\Role;
use App\Enums\Tracking;
use App\Models\Attribute;
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
        Supplier::query()->create(['name' => 'Malika bozori — Rustam aka']);
    }
}
