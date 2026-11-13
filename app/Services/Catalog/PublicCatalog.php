<?php

namespace App\Services\Catalog;

use App\Enums\StockStatus;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\StockBatch;
use App\Models\StockUnit;
use App\Models\Tenant;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;

/**
 * Mijozlar uchun ochiq katalog (Telegram mini-ilova): do'konda hozir bor tovarlar va narxi.
 *
 * Faqat ko'rish uchun. Tannarx, IMEI, yetkazib beruvchi va chegirma chegarasi bu yerga
 * hech qachon kirmaydi — sahifa havolasi bor har kimga ochiq.
 */
class PublicCatalog
{
    public function __construct(private readonly TenantContext $context) {}

    public function isEnabled(Tenant $tenant): bool
    {
        return (bool) $tenant->setting('catalog.enabled', false) && filled($tenant->setting('catalog.code'));
    }

    /**
     * Katalogni yoqadi yoki o'chiradi. Havola kodi bir marta yaratiladi va o'zgarmaydi,
     * shunda mijozlarga tarqatilgan havola qayta yoqilganda ham ishlaydi.
     */
    public function setEnabled(Tenant $tenant, bool $enabled): void
    {
        $settings = $tenant->settings ?? [];
        $settings['catalog'] = [
            'enabled' => $enabled,
            'code' => $tenant->setting('catalog.code') ?: Str::lower(Str::random(12)),
        ];

        $tenant->update(['settings' => $settings]);
    }

    public function findByCode(string $code): ?Tenant
    {
        if (! preg_match('/^[a-z0-9]{12}$/', $code)) {
            return null;
        }

        $tenant = Tenant::query()->where('settings->catalog->code', $code)->first();

        return $tenant !== null && $this->isEnabled($tenant) && ! $tenant->isBlocked() ? $tenant : null;
    }

    public function url(Tenant $tenant): ?string
    {
        return $this->isEnabled($tenant) ? route('storefront.show', (string) $tenant->setting('catalog.code')) : null;
    }

    /**
     * @return array{store: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function for(Tenant $tenant): array
    {
        return $this->context->runFor($tenant, function () use ($tenant): array {
            $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();
            $branchNames = $branches->pluck('name', 'id');
            $items = [];

            // Telefonlar: bir xil model, kriteriya va narxdagi donalar bitta qatorga yig'iladi.
            $units = StockUnit::query()
                ->with(['product.category:id,name', 'photos'])
                ->where('status', StockStatus::InStock)
                ->whereIn('branch_id', $branches->modelKeys())
                ->get();

            foreach ($units as $unit) {
                $attributes = $unit->attributes ?? [];
                $key = implode('|', ['u', $unit->product_id, $unit->branch_id, $unit->sale_currency->value, $unit->sale_price, json_encode($attributes)]);

                $items[$key] ??= [
                    'name' => $unit->product->name,
                    'image_url' => $unit->imageUrl(),
                    'category' => $unit->product->category->name,
                    'attributes' => (object) $attributes,
                    'price_label' => Money::of($unit->sale_price, $unit->sale_currency)->format(),
                    'price' => (float) $unit->sale_price,
                    'currency' => $unit->sale_currency->value,
                    'warranty_months' => $unit->warranty_months,
                    'branch' => (string) $branchNames[$unit->branch_id],
                    'qty' => 0,
                ];
                $items[$key]['qty']++;
            }

            // Aksesuarlar: qoldig'i bor variantlar.
            $quantities = StockBatch::query()
                ->whereIn('branch_id', $branches->modelKeys())
                ->where('qty_left', '>', 0)
                ->selectRaw('variant_id, branch_id, sum(qty_left) as qty')
                ->groupBy('variant_id', 'branch_id')
                ->get();

            $variants = ProductVariant::query()
                ->with('product.category:id,name')
                ->whereIn('id', $quantities->pluck('variant_id')->unique())
                ->get()
                ->keyBy('id');

            foreach ($quantities as $row) {
                $variant = $variants->get($row->variant_id);

                if ($variant === null) {
                    continue;
                }

                $items["v|{$variant->id}|{$row->branch_id}"] = [
                    'name' => $variant->product->name,
                    'image_url' => $variant->product->imageUrl(),
                    'category' => $variant->product->category->name,
                    'attributes' => (object) ($variant->attributes ?? []),
                    'price_label' => Money::of($variant->sale_price, $variant->sale_currency)->format(),
                    'price' => (float) $variant->sale_price,
                    'currency' => $variant->sale_currency->value,
                    'warranty_months' => $variant->warranty_months,
                    'branch' => (string) $branchNames[$row->branch_id],
                    'qty' => (int) $row->getAttribute('qty'),
                ];
            }

            $items = array_values($items);
            usort($items, fn (array $a, array $b) => [$a['category'], $a['name'], $a['price']] <=> [$b['category'], $b['name'], $b['price']]);

            return [
                'store' => [
                    'name' => (string) $tenant->setting('receipt.name', $tenant->name),
                    'phone' => $tenant->setting('receipt.phone'),
                    'branches' => $branches->map(fn (Branch $branch) => [
                        'name' => $branch->name,
                        'address' => $branch->address,
                        'phone' => $branch->phone,
                    ])->values()->all(),
                ],
                // Aniq qoldiq raqobatchilarga ko'rinmasligi uchun faqat "bor" belgisi beriladi.
                'items' => array_map(fn (array $item) => [...array_diff_key($item, ['qty' => true, 'price' => true]), 'few' => $item['qty'] === 1], $items),
            ];
        });
    }
}
