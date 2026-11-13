<?php

namespace App\Services\Installments;

use App\Enums\Currency;

/**
 * Sotuvchi tanlagan nasiya shartlari.
 */
final readonly class InstallmentTerms
{
    public function __construct(
        public Currency $currency,
        public int $months,
        public int $payDay,
        public string $downPayment = '0',
    ) {}
}
