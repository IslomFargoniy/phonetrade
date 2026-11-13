<?php

namespace App\Services\Sales;

use App\Enums\Currency;
use App\Exceptions\BusinessRuleException;
use App\Models\ProductVariant;
use App\Models\StockUnit;
use App\Support\Money;

/**
 * Savat hisobi. Kassa oynasidagi oldindan ko'rish ham, savdoni yopish ham shu
 * klassdan foydalanadi, shuning uchun ekrandagi va saqlangan raqamlar bir xil.
 *
 * Qoidalar:
 * - har tovar o'z valyutasida narxlanadi;
 * - savat bir valyutada bo'lsa jami shu valyutada, aralash bo'lsa so'mda
 *   (dollarli tovar savdo kunidagi kurs bilan);
 * - chegirma qatorning max chegirma %idan osha olmaydi.
 */
class CartCalculator
{
    /**
     * @param  list<array{unit?: StockUnit|null, variant?: ProductVariant|null, qty: int, discount: string}>  $items
     */
    public function calculate(array $items, ?string $rate): CartTotals
    {
        if ($items === []) {
            throw new BusinessRuleException('CART_EMPTY', "Savat bo'sh.", 'items');
        }

        $currency = $this->saleCurrency($items);
        $lines = [];
        $subtotal = Money::zero($currency);
        $total = Money::zero($currency);

        foreach ($items as $item) {
            $unit = $item['unit'] ?? null;
            $variant = $item['variant'] ?? null;
            $source = $unit ?? $variant;

            if ($source === null) {
                throw new BusinessRuleException('CART_INVALID', 'Savatdagi tovar topilmadi.', 'items');
            }

            $qty = $unit !== null ? 1 : $item['qty'];
            $price = Money::of($source->sale_price, $source->sale_currency);
            $gross = $price->times($qty);
            $discount = Money::of($item['discount'] !== '' ? $item['discount'] : '0', $source->sale_currency);
            $maxDiscount = $gross->percent($source->max_discount_pct);
            $name = $unit !== null ? $unit->product->name : $variant->product->name;

            if ($discount->isNegative() || $discount->greaterThan($maxDiscount)) {
                throw new BusinessRuleException(
                    'DISCOUNT_LIMIT_EXCEEDED',
                    sprintf('%s: chegirma %s dan oshmasligi kerak.', $name, $maxDiscount->format()),
                    'items',
                );
            }

            $net = $gross->minus($discount);
            $final = $this->convert($net, $currency, $rate);

            $lines[] = new CartLine($name, $qty, $price, $gross, $discount, $maxDiscount, $net, $final, $unit, $variant);

            $subtotal = $subtotal->plus($this->convert($gross, $currency, $rate));
            $total = $total->plus($final);
        }

        return new CartTotals($currency, $lines, $subtotal, $subtotal->minus($total), $total);
    }

    /**
     * To'lov summasini savdo valyutasiga o'tkazadi.
     */
    public function convert(Money $amount, Currency $target, ?string $rate): Money
    {
        if ($amount->currency === $target) {
            return $amount;
        }

        if ($rate === null) {
            throw new BusinessRuleException(
                'RATE_MISSING',
                'Dollar kursi kiritilmagan. Avval Sozlamalarda kursni kiriting.',
                'rate',
            );
        }

        return $amount->convertTo($target, $rate);
    }

    /**
     * @param  list<array{unit?: StockUnit|null, variant?: ProductVariant|null, qty: int, discount: string}>  $items
     */
    private function saleCurrency(array $items): Currency
    {
        $currencies = [];

        foreach ($items as $item) {
            $source = $item['unit'] ?? $item['variant'] ?? null;

            if ($source !== null) {
                $currencies[$source->sale_currency->value] = $source->sale_currency;
            }
        }

        return count($currencies) === 1 ? array_values($currencies)[0] : Currency::UZS;
    }
}
