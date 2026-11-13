<?php

namespace App\Services\Sales;

use App\Models\ProductVariant;
use App\Models\StockUnit;
use App\Support\Money;

/**
 * Savatdagi bitta qatorning hisoblangan ko'rinishi.
 */
final readonly class CartLine
{
    public function __construct(
        public string $name,
        public int $qty,
        /** Bir dona narxi, tovarning o'z valyutasida */
        public Money $price,
        /** Chegirmasiz qator summasi */
        public Money $gross,
        public Money $discount,
        /** Shu qatorga berish mumkin bo'lgan eng katta chegirma */
        public Money $maxDiscount,
        /** Chegirmadan keyin, tovar valyutasida */
        public Money $net,
        /** Chegirmadan keyin, savdo valyutasida */
        public Money $final,
        public ?StockUnit $unit = null,
        public ?ProductVariant $variant = null,
    ) {}
}
