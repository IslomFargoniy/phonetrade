<?php

namespace App\Console\Commands;

use App\Actions\Branches\CreateBranch;
use App\Actions\Cash\RecordExpense;
use App\Actions\Cash\SweepCash;
use App\Actions\Installments\AcceptInstallmentPayment;
use App\Actions\Sales\CloseSale;
use App\Actions\Sales\ReturnSale;
use App\Actions\Stock\ReceiveStock;
use App\Actions\Stock\ReceiveTransfer;
use App\Actions\Stock\SendTransfer;
use App\Enums\AttributeType;
use App\Enums\CashAccountType;
use App\Enums\Currency;
use App\Enums\PlanStatus;
use App\Enums\Role;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Enums\StockStatus;
use App\Enums\Tracking;
use App\Exceptions\BusinessRuleException;
use App\Models\Attribute;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Category;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\StockBatch;
use App\Models\StockTransfer;
use App\Models\StockUnit;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Cash\CashService;
use App\Services\Installments\InstallmentTerms;
use App\Services\Rates\CurrencyRateService;
use App\Services\Sales\CartPreview;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Namuna do'konni o'tgan kunlar uchun ma'lumot bilan to'ldiradi: kirim, sotuv, nasiya,
 * to'lovlar, xarajat, kassa bo'shatish, ko'chirish va qaytarish.
 *
 * Yozuvlar to'g'ridan-to'g'ri jadvalga emas, tizimning o'z amallari orqali yaratiladi —
 * shunda kassa, ombor va hisobotlar haqiqiy ishdagidek bir-biriga mos chiqadi.
 * Har kun uchun vaqt o'sha kunga suriladi, shuning uchun sanalar ham to'g'ri bo'ladi.
 */
#[Signature('demo:fill {tenant : Do\'kon ID} {--days=30 : Necha kunlik ma\'lumot} {--force : Do\'konda savdolar bo\'lsa ham davom etish}')]
#[Description('Namuna do\'konni o\'tgan kunlar uchun to\'liq jarayon ma\'lumotlari bilan to\'ldiradi')]
class FillDemoData extends Command
{
    private User $owner;

    /** @var array<int, User> filial ID => shu filial sotuvchisi */
    private array $sellers = [];

    private int $imeiCounter = 0;

    /** Ma'lumot to'ldiriladigan davrning oxirgi kuni (bugun). */
    private CarbonImmutable $end;

    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<int, array{Branch, string, string, string}> kassada pul yetmay keyinga qolgan xarajatlar */
    private array $pendingExpenses = [];

    public function handle(TenantContext $context): int
    {
        $tenant = Tenant::query()->find((int) $this->argument('tenant'));

        if ($tenant === null) {
            $this->error("Do'kon topilmadi.");

            return self::FAILURE;
        }

        $days = max(7, min(90, (int) $this->option('days')));

        $exit = $context->runFor($tenant, function () use ($days): int {
            if (Sale::query()->count() > 20 && ! $this->option('force')) {
                $this->error("Bu do'konda allaqachon ko'p savdo bor. Baribir qo'shish uchun --force bering.");

                return self::FAILURE;
            }

            // Natija har safar bir xil chiqishi uchun tasodifiy sonlar qat'iy urug'dan boshlanadi.
            mt_srand(20261114);
            $this->imeiCounter = (int) StockUnit::query()->max('id') + 1000;

            try {
                $this->simulate($days);
            } finally {
                Carbon::setTestNow();
            }

            return self::SUCCESS;
        });

        if ($exit === self::SUCCESS) {
            $this->table(['Jarayon', 'Soni'], collect($this->counts)->map(fn (int $count, string $name) => [$name, $count])->values()->all());
        }

        return $exit;
    }

    private function simulate(int $days): void
    {
        $this->end = CarbonImmutable::today();
        $start = $this->end->subDays($days - 1);

        $this->at($start, 8, 30);
        $this->owner = User::query()->where('role', Role::Owner)->where('is_active', true)->firstOrFail();
        $this->setRate(0);
        [$main, $second] = $this->prepareStore();
        $products = $this->prepareCatalog();
        $customers = $this->prepareCustomers();
        $suppliers = [
            Supplier::query()->firstOrCreate(['name' => 'Malika bozori — Rustam aka'], ['phone' => '998901002030']),
            Supplier::query()->firstOrCreate(['name' => 'Abu Saxiy — Jahongir'], ['phone' => '998935004060']),
        ];
        $expenseCategories = collect(['Ijara', 'Maosh', 'Kommunal', 'Reklama', 'Boshqa'])
            ->mapWithKeys(fn (string $name) => [$name => ExpenseCategory::query()->firstOrCreate(['name' => $name])->id]);

        $pendingTransfer = null;

        for ($day = 0; $day < $days; $day++) {
            $date = $start->addDays($day);

            $this->at($date, 8, 45);
            $this->setRate($day);

            // Kirim: har 5 kunda yetkazib beruvchidan, birinchi kuni boshlang'ich qoldiq ham.
            if ($day % 5 === 0) {
                $this->at($date, 9, 10);
                $this->receivePhones($main, $products, $suppliers[$day % 2], $day === 0 ? 34 : mt_rand(20, 26));
                $this->receiveAccessories($main, $products, $suppliers[($day + 1) % 2]);
            }

            // Ko'chirish: ikkinchi filialga tovar yuboriladi, ertasi kuni qabul qilinadi.
            if ($pendingTransfer !== null) {
                $this->at($date, 9, 40);
                $this->attempt('Ko\'chirishni qabul qilish', fn () => app(ReceiveTransfer::class)->handle($pendingTransfer, $this->sellers[$second->id]));
                $pendingTransfer = null;
            }

            if (in_array($day, [1, 9, 17, 24], true)) {
                $this->at($date, 17, 30);
                $pendingTransfer = $this->sendTransfer($main, $second);
            }

            // Mijozdan telefon sotib olish (trade-in): pul filial kassasidan chiqadi.
            if (in_array($day, [6, 13, 21], true)) {
                $this->at($date, 11, 20);
                $this->tradeIn($main, $products, $customers[array_rand($customers)]);
            }

            // Sotuvlar.
            foreach ([$main, $second] as $branch) {
                $sales = $branch->is($main) ? mt_rand(3, 6) : mt_rand(1, 3);

                for ($i = 0; $i < $sales; $i++) {
                    $this->at($date, mt_rand(10, 19), mt_rand(0, 59));
                    $this->sell($branch, $customers, $day, $days);
                }
            }

            // Nasiya to'lovlari: muddati kelganlarning bir qismi to'laydi, qolgani kechikadi.
            $this->at($date, 15, mt_rand(0, 50));
            $this->collectInstalments($date);

            // Qaytarish.
            if (in_array($day, [8, 19], true)) {
                $this->at($date, 16, 10);
                $this->returnSale($main);
            }

            // Xarajatlar.
            $this->at($date, 18, 30);
            $this->expenses($main, $second, $expenseCategories->all(), $day, $days);

            // Kassa bo'shatish: haftada bir marta filial kassasi admin kassaga o'tkaziladi.
            if ($day % 7 === 4) {
                $this->at($date, 20, 5);
                $this->attempt('Kassa bo\'shatish', fn () => app(SweepCash::class)->handle($main, $this->owner));
                $this->attempt('Kassa bo\'shatish', fn () => app(SweepCash::class)->handle($second, $this->owner));
            }
        }
    }

    /**
     * @return array{Branch, Branch}
     */
    private function prepareStore(): array
    {
        $main = Branch::query()->where('is_active', true)->orderBy('id')->firstOrFail();

        $second = Branch::query()->where('is_active', true)->where('id', '!=', $main->id)->orderBy('id')->first()
            ?? app(CreateBranch::class)->handle(['name' => 'Yunusobod filiali', 'address' => 'Toshkent, Amir Temur ko\'chasi 108', 'phone' => '998712000202']);

        if ($main->address === null) {
            $main->update(['address' => 'Toshkent, Chilonzor 5-mavze, Malika savdo majmuasi', 'phone' => '998712000101']);
        }

        $this->sellers[$main->id] = $this->seller('Sardor Aliyev', '998900001101', $main);
        $this->sellers[$second->id] = $this->seller('Dilnoza Karimova', '998900001102', $second);

        return [$main, $second];
    }

    private function seller(string $name, string $phone, Branch $branch): User
    {
        $seller = User::query()->firstOrCreate(['phone' => $phone], [
            'name' => $name,
            'password' => Str::random(40),
            'role' => Role::Seller,
            'can_refund' => true,
        ]);
        $seller->branches()->syncWithoutDetaching([$branch->id]);

        return $seller;
    }

    /**
     * @return array{phones: list<array{product: Product, options: list<array<string, mixed>>}>, accessories: list<array<string, mixed>>}
     */
    private function prepareCatalog(): array
    {
        $phones = Category::query()->firstOrCreate(['name' => 'Telefon'], ['tracking' => Tracking::Serial]);
        $accessories = Category::query()->firstOrCreate(['name' => 'Aksesuar'], ['tracking' => Tracking::Quantity]);

        foreach (['Xotira' => ['128 GB', '256 GB', '8/256 GB'], 'Rang' => ['Qora', 'Oq', "Ko'k", 'Kumush'], 'Holat' => ['Yangi', 'Ishlatilgan A', 'Ishlatilgan B']] as $sort => $options) {
            $name = (string) $sort;
            $attribute = Attribute::query()->firstOrCreate(['name' => $name], ['type' => AttributeType::Select, 'sort' => Attribute::query()->count()]);
            $attribute->categories()->syncWithoutDetaching($name === 'Rang' ? [$phones->id, $accessories->id] : [$phones->id]);

            foreach ($options as $index => $value) {
                $attribute->options()->firstOrCreate(['value' => $value], ['sort' => $index]);
            }
        }

        $phone = fn (string $name, int $warranty, string $discount) => Product::query()->firstOrCreate(
            ['name' => $name],
            ['category_id' => $phones->id, 'default_warranty_months' => $warranty, 'default_max_discount_pct' => $discount],
        );
        $accessory = fn (string $name) => Product::query()->firstOrCreate(['name' => $name], ['category_id' => $accessories->id]);

        // Tannarx dollarda (bozordan dollarda olinadi), sotuv narxi asosan so'mda.
        return [
            'phones' => [
                ['product' => $phone('Samsung Galaxy A55', 12, '15'), 'options' => [
                    ['attributes' => ['Xotira' => '8/256 GB', 'Rang' => 'Qora', 'Holat' => 'Yangi'], 'cost' => '320', 'price' => '4850000', 'currency' => 'UZS'],
                    ['attributes' => ['Xotira' => '8/256 GB', 'Rang' => "Ko'k", 'Holat' => 'Yangi'], 'cost' => '322', 'price' => '4900000', 'currency' => 'UZS'],
                ]],
                ['product' => $phone('Samsung Galaxy S24', 12, '10'), 'options' => [
                    ['attributes' => ['Xotira' => '256 GB', 'Rang' => 'Qora', 'Holat' => 'Yangi'], 'cost' => '640', 'price' => '9400000', 'currency' => 'UZS'],
                ]],
                ['product' => $phone('iPhone 15', 12, '8'), 'options' => [
                    ['attributes' => ['Xotira' => '128 GB', 'Rang' => 'Qora', 'Holat' => 'Yangi'], 'cost' => '720', 'price' => '820', 'currency' => 'USD'],
                    ['attributes' => ['Xotira' => '256 GB', 'Rang' => 'Oq', 'Holat' => 'Yangi'], 'cost' => '810', 'price' => '920', 'currency' => 'USD'],
                ]],
                ['product' => $phone('iPhone 13', 3, '10'), 'options' => [
                    ['attributes' => ['Xotira' => '128 GB', 'Rang' => 'Oq', 'Holat' => 'Ishlatilgan A'], 'cost' => '380', 'price' => '460', 'currency' => 'USD'],
                    ['attributes' => ['Xotira' => '128 GB', 'Rang' => 'Qora', 'Holat' => 'Ishlatilgan B'], 'cost' => '330', 'price' => '410', 'currency' => 'USD'],
                ]],
                ['product' => $phone('Redmi Note 13', 12, '12'), 'options' => [
                    ['attributes' => ['Xotira' => '8/256 GB', 'Rang' => 'Qora', 'Holat' => 'Yangi'], 'cost' => '185', 'price' => '2900000', 'currency' => 'UZS'],
                    ['attributes' => ['Xotira' => '8/256 GB', 'Rang' => "Ko'k", 'Holat' => 'Yangi'], 'cost' => '187', 'price' => '2950000', 'currency' => 'UZS'],
                ]],
                ['product' => $phone('Tecno Spark 20', 12, '12'), 'options' => [
                    ['attributes' => ['Xotira' => '256 GB', 'Rang' => 'Kumush', 'Holat' => 'Yangi'], 'cost' => '118', 'price' => '1850000', 'currency' => 'UZS'],
                ]],
            ],
            'accessories' => [
                ['product' => $accessory('Chexol Galaxy A55'), 'barcode' => '4780012345678', 'cost' => '20000', 'price' => '60000', 'qty' => 30],
                ['product' => $accessory('Himoya oynasi (universal)'), 'barcode' => '4780012345685', 'cost' => '8000', 'price' => '30000', 'qty' => 60],
                ['product' => $accessory('Zaryadlovchi 25W'), 'barcode' => '4780012345692', 'cost' => '55000', 'price' => '120000', 'qty' => 25],
                ['product' => $accessory('Simsiz quloqchin'), 'barcode' => '4780012345708', 'cost' => '140000', 'price' => '260000', 'qty' => 15],
                ['product' => $accessory('Powerbank 10 000 mAh'), 'barcode' => '4780012345715', 'cost' => '110000', 'price' => '210000', 'qty' => 12],
            ],
        ];
    }

    /**
     * @return list<Customer>
     */
    private function prepareCustomers(): array
    {
        $people = [
            ['Aliyev Jasur Baxtiyorovich', 'Chilonzor tumani, 7-mavze, 12-uy'],
            ['Karimova Nodira Rustamovna', 'Yunusobod tumani, 4-mavze, 35-uy'],
            ['Tursunov Bekzod Alisherovich', 'Sergeli tumani, 5-mavze, 8-uy'],
            ['Rahimov Otabek Shavkatovich', 'Mirzo Ulug\'bek tumani, Buyuk Ipak Yo\'li 45'],
            ['Yusupova Malika Anvarovna', 'Shayxontohur tumani, Labzak ko\'chasi 21'],
            ['Xolmatov Sherzod Ilhomovich', 'Olmazor tumani, Qorasaroy ko\'chasi 14'],
            ['Ismoilova Gulnoza Farhodovna', 'Yakkasaroy tumani, Bobur ko\'chasi 60'],
            ['Normatov Azizbek Ulug\'bekovich', 'Uchtepa tumani, 22-mavze, 5-uy'],
            ['Saidova Zarina Komilovna', 'Mirobod tumani, Nukus ko\'chasi 33'],
            ['Qodirov Farrux Baxodirovich', 'Bektemir tumani, Husayn Boyqaro 9'],
            ['Abdullayeva Sevara Olimovna', 'Yashnobod tumani, Parkent ko\'chasi 77'],
            ['Mirzayev Davron Nematovich', 'Chilonzor tumani, 19-mavze, 41-uy'],
            ['Ergasheva Dilfuza Sobirovna', 'Yunusobod tumani, 11-mavze, 16-uy'],
            ['Hakimov Sanjar Rahmatovich', 'Sergeli tumani, 8-mavze, 23-uy'],
        ];

        $customers = [];

        foreach ($people as $index => [$name, $address]) {
            $customers[] = Customer::query()->firstOrCreate(['phone' => sprintf('99890700%04d', 1101 + $index * 37)], [
                'full_name' => $name,
                'passport_series' => ['AA', 'AB', 'AC', 'AD'][$index % 4],
                'passport_number' => sprintf('%07d', 1200345 + $index * 48211),
                'pinfl' => sprintf('3%013d', 1019900000000 + $index * 7300417),
                'address' => 'Toshkent, '.$address,
            ]);
        }

        $this->count('Mijozlar', count($customers));

        return $customers;
    }

    /**
     * @param  array{phones: list<array{product: Product, options: list<array<string, mixed>>}>, accessories: list<array<string, mixed>>}  $products
     */
    private function receivePhones(Branch $branch, array $products, Supplier $supplier, int $total): void
    {
        $lines = [];

        for ($i = 0; $i < $total; $i++) {
            $entry = $products['phones'][mt_rand(0, count($products['phones']) - 1)];
            $option = $entry['options'][mt_rand(0, count($entry['options']) - 1)];
            $key = $entry['product']->id.'|'.json_encode($option['attributes']);

            $lines[$key] ??= [
                'product_id' => $entry['product']->id,
                'attributes' => $option['attributes'],
                'unit_cost' => $option['cost'],
                'sale_price' => $option['price'],
                'sale_currency' => $option['currency'],
                'max_discount_pct' => $entry['product']->default_max_discount_pct,
                'warranty_months' => $entry['product']->default_warranty_months,
                'units' => [],
            ];
            $lines[$key]['units'][] = ['imei1' => $this->imei()];
        }

        $this->attempt('Kirim (telefonlar)', fn () => app(ReceiveStock::class)->handle($branch, $this->owner, [
            'type' => 'supplier',
            'currency' => 'USD',
            'supplier_id' => $supplier->id,
            'note' => 'Bozordan olib kelindi',
            'lines' => array_values($lines),
        ]));
    }

    /**
     * @param  array{phones: list<array<string, mixed>>, accessories: list<array<string, mixed>>}  $products
     */
    private function receiveAccessories(Branch $branch, array $products, Supplier $supplier): void
    {
        $lines = [];

        foreach ($products['accessories'] as $accessory) {
            $left = (int) StockBatch::query()
                ->where('branch_id', $branch->id)
                ->whereIn('variant_id', ProductVariant::query()->where('barcode', $accessory['barcode'])->select('id'))
                ->sum('qty_left');

            // Faqat kamayib qolgan aksesuar to'ldiriladi. Barcode do'konda allaqachon bo'lsa, o'sha mahsulotga kirim qilinadi.
            if ($left < $accessory['qty'] / 3) {
                $lines[] = [
                    'product_id' => ProductVariant::query()->where('barcode', $accessory['barcode'])->value('product_id') ?? $accessory['product']->id,
                    'barcode' => $accessory['barcode'],
                    'unit_cost' => $accessory['cost'],
                    'sale_price' => $accessory['price'],
                    'sale_currency' => 'UZS',
                    'qty' => $accessory['qty'],
                ];
            }
        }

        if ($lines !== []) {
            $this->attempt('Kirim (aksesuarlar)', fn () => app(ReceiveStock::class)->handle($branch, $this->owner, [
                'type' => 'supplier', 'currency' => 'UZS', 'supplier_id' => $supplier->id, 'lines' => $lines,
            ]));
        }
    }

    private function sendTransfer(Branch $from, Branch $to): ?StockTransfer
    {
        $unitIds = StockUnit::query()->where('branch_id', $from->id)->where('status', StockStatus::InStock)
            ->inRandomOrder()->limit(mt_rand(3, 5))->pluck('id')->all();

        $quantities = [];

        foreach (StockBatch::query()->where('branch_id', $from->id)->where('qty_left', '>', 6)->get()->groupBy('variant_id') as $variantId => $batches) {
            $quantities[] = ['variant_id' => (int) $variantId, 'qty' => min(5, (int) floor($batches->sum('qty_left') / 3))];
        }

        if ($unitIds === []) {
            return null;
        }

        return $this->attempt('Ko\'chirish yuborish', fn () => app(SendTransfer::class)->handle(
            $from, $to, $this->owner, array_values(array_map(intval(...), $unitIds)), array_slice($quantities, 0, 3), $to->name.'ga',
        ));
    }

    /**
     * @param  array{phones: list<array{product: Product, options: list<array<string, mixed>>}>, accessories: list<array<string, mixed>>}  $products
     */
    private function tradeIn(Branch $branch, array $products, Customer $customer): void
    {
        $entry = collect($products['phones'])->firstWhere('product.name', 'iPhone 13') ?? $products['phones'][0];
        $account = $this->account($branch, CashAccountType::Cash);

        if (app(CashService::class)->balance($account, Currency::UZS)->lessThan(Money::uzs('4500000'))) {
            return;
        }

        $this->attempt('Mijozdan sotib olish', fn () => app(ReceiveStock::class)->handle($branch, $this->owner, [
            'type' => 'trade_in',
            'currency' => 'UZS',
            'customer_id' => $customer->id,
            'cash_account_id' => $account->id,
            'note' => 'Mijozdan sotib olindi, holati tekshirildi',
            'lines' => [[
                'product_id' => $entry['product']->id,
                'attributes' => ['Xotira' => '128 GB', 'Rang' => 'Qora', 'Holat' => 'Ishlatilgan B'],
                'unit_cost' => (string) (mt_rand(38, 44) * 100000),
                'sale_price' => (string) mt_rand(400, 430),
                'sale_currency' => 'USD',
                'max_discount_pct' => '10',
                'warranty_months' => 1,
                'units' => [['imei1' => $this->imei()]],
            ]],
        ]));
    }

    /**
     * @param  list<Customer>  $customers
     */
    private function sell(Branch $branch, array $customers, int $day, int $days): void
    {
        $items = [];
        $unit = StockUnit::query()->where('branch_id', $branch->id)->where('status', StockStatus::InStock)->inRandomOrder()->first();

        // Ko'pincha telefon, ba'zan faqat aksesuar; telefon bilan birga ko'pincha aksesuar ham olinadi.
        if ($unit !== null && mt_rand(1, 100) <= 78) {
            $items[] = ['stock_unit_id' => $unit->id, 'variant_id' => null, 'qty' => 1, 'discount' => '0'];
        }

        if ($items === [] || mt_rand(1, 100) <= 45) {
            $batch = StockBatch::query()->where('branch_id', $branch->id)->where('qty_left', '>', 0)->inRandomOrder()->first();

            if ($batch !== null) {
                $items[] = ['stock_unit_id' => null, 'variant_id' => $batch->variant_id, 'qty' => min(mt_rand(1, 2), $batch->qty_left), 'discount' => '0'];
            }
        }

        if ($items === []) {
            return;
        }

        // Muddatli savdo: telefon bo'lsa, oyning boshida ko'proq (to'lov muddatlari shu oy ichida kelishi uchun).
        $installment = isset($items[0]['stock_unit_id']) && mt_rand(1, 100) <= ($day < 6 ? 55 : 22);
        $customer = $installment || mt_rand(1, 100) <= 30 ? $customers[array_rand($customers)] : null;
        $terms = null;

        $this->attempt($installment ? 'Muddatli savdo' : 'To\'liq to\'lovli savdo', function () use ($branch, &$items, $installment, $customer, &$terms): Sale {
            $preview = app(CartPreview::class);
            $totals = $preview->for($branch, $items, null);

            // Ba'zan chegirma beriladi: ruxsat etilgan chegaraning bir qismi, tovar valyutasida, yaxlit summa.
            if (mt_rand(1, 100) <= 25 && isset($items[0]['stock_unit_id'])) {
                $unit = StockUnit::query()->findOrFail($items[0]['stock_unit_id']);
                $step = $unit->sale_currency === Currency::USD ? 5 : 50000;
                $limit = (float) $unit->sale_price * (float) $unit->max_discount_pct / 100;
                $discount = floor($limit * mt_rand(20, 60) / 100 / $step) * $step;

                if ($discount > 0) {
                    $items[0]['discount'] = (string) (int) $discount;
                    $totals = $preview->for($branch, $items, null);
                }
            }

            if ($installment) {
                $months = [3, 6, 6, 9, 12][mt_rand(0, 4)];
                $now = CarbonImmutable::now();
                $down = mt_rand(1, 100) <= 45
                    ? (string) ($totals['currency'] === 'USD' ? mt_rand(1, 3) * 100 : mt_rand(5, 15) * 100000)
                    : '0';
                // To'lov kuni: o'tgan oyda ochilgan shartnomalarda davr oxiriga yaqin kun tanlanadi —
                // shunda navbatdagi to'lovlarning bir qismi to'langan, bir qismi bugungi yoki kechikkan bo'lib chiqadi.
                $payDay = $now->isSameMonth($this->end)
                    ? min(28, $now->day)
                    : mt_rand(max(1, $this->end->day - 12), min(28, $this->end->day + 2));
                $terms = new InstallmentTerms(Currency::from($totals['currency']), $months, $payDay, $down);
                $totals = $preview->for($branch, $items, $terms);
            }

            $due = $installment ? (string) $totals['installment']['due_today'] : (string) $totals['total'];
            $currency = $installment ? (string) $totals['installment']['currency'] : (string) $totals['currency'];

            return app(CloseSale::class)->handle(
                $branch,
                mt_rand(1, 100) <= 30 && $branch->id === array_key_first($this->sellers) ? $this->owner : $this->sellers[$branch->id],
                $items,
                $this->payments($due, $currency),
                $customer?->id,
                $terms,
            );
        });
    }

    /**
     * To'lovni usullarga bo'ladi: ko'pincha bitta usul, ba'zan naqd + karta.
     *
     * @return list<array{method: string, currency: string, amount: string}>
     */
    private function payments(string $due, string $currency): array
    {
        if ((float) $due <= 0) {
            return [];
        }

        $roll = mt_rand(1, 100);
        $method = $currency === 'USD' ? 'cash' : ($roll <= 55 ? 'cash' : ($roll <= 88 ? 'card' : 'transfer'));

        // Katta so'm summalarining bir qismi ikki usulda to'lanadi.
        if ($currency === 'UZS' && (float) $due > 2000000 && mt_rand(1, 100) <= 18) {
            $cash = (string) (floor((float) $due / 2 / 100000) * 100000);

            return [
                ['method' => 'cash', 'currency' => 'UZS', 'amount' => $cash],
                ['method' => 'card', 'currency' => 'UZS', 'amount' => Money::uzs($due)->minus(Money::uzs($cash))->amount],
            ];
        }

        return [['method' => $method, 'currency' => $currency, 'amount' => $due]];
    }

    private function collectInstalments(CarbonImmutable $date): void
    {
        $plans = InstallmentPlan::query()
            ->with(['branch', 'schedule' => fn ($schedule) => $schedule->orderBy('no')])
            ->where('status', PlanStatus::Active)
            ->get();

        foreach ($plans as $plan) {
            $due = $plan->schedule->first(
                fn (InstallmentScheduleItem $row) => Money::of($row->paid_amount, $plan->currency)->lessThan(Money::of($row->amount, $plan->currency)),
            );

            if ($due === null || $due->due_date->startOfDay()->greaterThan($date)) {
                continue;
            }

            $late = (int) $due->due_date->startOfDay()->diffInDays($date);

            // To'lov kunida ko'pchilik to'laydi; kechikkanlarning bir qismi keyingi kunlarda, ayrimlari qarzdor bo'lib qoladi.
            $pays = $late === 0 ? mt_rand(1, 100) <= 62 : mt_rand(1, 100) <= 14;

            if (! $pays || $plan->id % 7 === 0) {
                continue;
            }

            $unpaid = Money::of($due->amount, $plan->currency)->minus(Money::of($due->paid_amount, $plan->currency));
            // Ba'zilar oylikning bir qismini to'laydi.
            $amount = mt_rand(1, 100) <= 15 && $plan->currency === Currency::UZS
                ? Money::uzs((string) (floor((float) $unpaid->amount / 2 / 10000) * 10000))
                : $unpaid;

            if (! $amount->isPositive()) {
                continue;
            }

            $this->attempt('Nasiya to\'lovi', fn () => app(AcceptInstallmentPayment::class)->handle(
                $plan, $plan->branch, $this->sellers[$plan->branch_id] ?? $this->owner, $amount,
                mt_rand(1, 100) <= 70 ? CashAccountType::Cash : CashAccountType::Card,
            ));
        }
    }

    private function returnSale(Branch $branch): void
    {
        $sale = Sale::query()
            ->with('items')
            ->where('branch_id', $branch->id)
            ->where('type', SaleType::Full)
            ->where('status', SaleStatus::Completed)
            ->where('currency', Currency::UZS)
            ->whereDate('created_at', '<', CarbonImmutable::today()->toDateString())
            ->whereHas('payments', fn ($payments) => $payments->where('method', CashAccountType::Cash))
            ->inRandomOrder()
            ->first();

        if ($sale === null) {
            return;
        }

        $item = $sale->items->first();

        $this->attempt('Qaytarish', fn () => app(ReturnSale::class)->handle(
            $sale, $this->owner, [['sale_item_id' => $item->id, 'qty' => 1]], CashAccountType::Cash, 'Mijoz fikridan qaytdi',
        ));
    }

    /**
     * @param  array<string, int>  $categories
     */
    private function expenses(Branch $main, Branch $second, array $categories, int $day, int $days): void
    {
        $plan = [];

        if ($day === 2) {
            $plan[] = [$main, 'Ijara', '6000000', $main->name.' ijarasi'];
            $plan[] = [$second, 'Ijara', '4500000', $second->name.' ijarasi'];
        }

        if ($day === 14 || $day === $days - 2) {
            $plan[] = [$main, 'Maosh', '3500000', 'Sardor — oylik (yarim)'];
            $plan[] = [$second, 'Maosh', '3200000', 'Dilnoza — oylik (yarim)'];
        }

        if ($day === 10) {
            $plan[] = [$main, 'Kommunal', '780000', 'Elektr va internet'];
            $plan[] = [$second, 'Kommunal', '540000', 'Elektr va internet'];
        }

        if ($day % 9 === 4) {
            $plan[] = [$main, 'Reklama', (string) (mt_rand(4, 9) * 100000), 'Instagram va Telegram reklama'];
        }

        if ($day % 6 === 3) {
            $plan[] = [$main, 'Boshqa', (string) (mt_rand(5, 20) * 10000), 'Choy, suv, xo\'jalik mollari'];
        }

        // Kassada pul yetmagan kuni xarajat keyingi kunga qoladi (haqiqiy ishda ham shunday).
        $this->pendingExpenses = [...$this->pendingExpenses, ...$plan];

        foreach ($this->pendingExpenses as $index => [$branch, $category, $amount, $note]) {
            $account = $this->account($branch, CashAccountType::Cash);

            if (app(CashService::class)->balance($account, Currency::UZS)->lessThan(Money::uzs($amount))) {
                continue;
            }

            $this->attempt('Xarajat', fn () => app(RecordExpense::class)->handle(
                $branch, $this->owner, $categories[$category], $account->id, Money::uzs($amount), $note,
            ));
            unset($this->pendingExpenses[$index]);
        }
    }

    private function setRate(int $day): void
    {
        // Kurs kun sayin biroz o'zgaradi.
        $rate = 12700 + $day * 3 + mt_rand(-12, 18);
        app(CurrencyRateService::class)->setManual((string) $rate, $this->owner);
    }

    private function account(Branch $branch, CashAccountType $type): CashAccount
    {
        return CashAccount::query()->where('branch_id', $branch->id)->where('type', $type)->firstOrFail();
    }

    private function at(CarbonImmutable $date, int $hour, int $minute): void
    {
        Carbon::setTestNow($date->setTime($hour, $minute));
    }

    /**
     * Amalni bajaradi; biznes qoidasi rad etsa (masalan, kassada pul yetmasa) o'tkazib yuboradi —
     * haqiqiy ishda ham shunday bo'ladi.
     *
     * @template T
     *
     * @param  callable(): T  $action
     * @return T|null
     */
    private function attempt(string $name, callable $action): mixed
    {
        try {
            $result = $action();
            $this->count($name);

            return $result;
        } catch (BusinessRuleException $exception) {
            $this->count("{$name} — rad etildi");

            return null;
        }
    }

    private function count(string $name, int $by = 1): void
    {
        $this->counts[$name] = ($this->counts[$name] ?? 0) + $by;
    }

    /**
     * Luhn bo'yicha to'g'ri, takrorlanmaydigan IMEI.
     */
    private function imei(): string
    {
        do {
            $body = sprintf('35847611%06d', ++$this->imeiCounter % 1000000);
            $sum = 0;

            foreach (str_split(strrev($body)) as $position => $char) {
                $digit = (int) $char;

                if ($position % 2 === 0) {
                    $digit *= 2;
                    $digit = $digit > 9 ? $digit - 9 : $digit;
                }

                $sum += $digit;
            }

            $imei = $body.((10 - $sum % 10) % 10);
        } while (StockUnit::query()->where('imei1', $imei)->exists());

        return $imei;
    }
}
