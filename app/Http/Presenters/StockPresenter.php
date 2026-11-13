<?php

namespace App\Http\Presenters;

use App\Enums\Currency;
use App\Models\ProductVariant;
use App\Models\StockUnit;
use App\Models\StockUnitPhoto;
use App\Support\Money;

/**
 * Ombor yozuvlarini sahifaga beriladigan ko'rinishga keltiradi.
 *
 * Tannarx faqat $withCost berilganda qo'shiladi. Sotuvchi uchun va kassa oynasida
 * bu maydonlar javobga umuman kirmaydi (interfeysda yashirish yetarli emas).
 */
final class StockPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function unit(StockUnit $unit, bool $withCost): array
    {
        $data = [
            'id' => $unit->id,
            'product' => $unit->product->name,
            'image_url' => $unit->imageUrl(),
            'photos' => $unit->photos->map(fn (StockUnitPhoto $photo) => ['id' => $photo->id, 'url' => $photo->url()])->values(),
            'branch_id' => $unit->branch_id,
            'branch' => $unit->branch->name,
            'imei1' => $unit->imei1,
            'imei2' => $unit->imei2,
            'barcode' => $unit->barcode,
            'attributes' => self::attributes($unit->attributes),
            'sale_price' => $unit->sale_price,
            'sale_currency' => $unit->sale_currency->value,
            'sale_price_label' => Money::of($unit->sale_price, $unit->sale_currency)->format(),
            'max_discount_pct' => self::trimDecimal($unit->max_discount_pct),
            'warranty_months' => $unit->warranty_months,
            'status' => $unit->status->value,
            'status_label' => $unit->status->label(),
            'received_at' => $unit->created_at?->format('d.m.Y'),
        ];

        if ($withCost) {
            $data['cost'] = $unit->cost;
            $data['cost_currency'] = $unit->cost_currency->value;
            $data['cost_label'] = Money::of($unit->cost, $unit->cost_currency)->format();
            $data['cost_rate'] = $unit->cost_rate;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function variant(ProductVariant $variant, int $qty): array
    {
        return [
            'id' => $variant->id,
            'product' => $variant->product->name,
            'image_url' => $variant->product->imageUrl(),
            'barcode' => $variant->barcode,
            'attributes' => self::attributes($variant->attributes),
            'sale_price' => $variant->sale_price,
            'sale_currency' => $variant->sale_currency->value,
            'sale_price_label' => Money::of($variant->sale_price, $variant->sale_currency)->format(),
            'max_discount_pct' => self::trimDecimal($variant->max_discount_pct),
            'qty' => $qty,
        ];
    }

    /**
     * Kriteriyalar har doim JSON obyekti bo'lib chiqadi: bo'sh bo'lsa ham `{}`, `[]` emas
     * (mobil ilova lug'at kutadi).
     *
     * @param  array<string, string>|null  $attributes
     */
    public static function attributes(?array $attributes): object
    {
        return (object) ($attributes ?? []);
    }

    public static function money(string $amount, Currency $currency): string
    {
        return Money::of($amount, $currency)->format();
    }

    public static function trimDecimal(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
