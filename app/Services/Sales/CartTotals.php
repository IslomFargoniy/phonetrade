<?php

namespace App\Services\Sales;

use App\Enums\Currency;
use App\Support\Money;

final readonly class CartTotals
{
    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public Currency $currency,
        public array $lines,
        public Money $subtotal,
        public Money $discount,
        public Money $total,
    ) {}
}
