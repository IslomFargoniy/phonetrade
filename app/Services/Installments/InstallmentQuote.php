<?php

namespace App\Services\Installments;

use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Hisoblangan nasiya shartlari: qarz, oylik va to'lov jadvali.
 */
final readonly class InstallmentQuote
{
    /**
     * @param  list<array{no: int, due_date: CarbonImmutable, amount: Money}>  $schedule
     */
    public function __construct(
        /** A — boshlang'ichsiz, B — boshlang'ich bilan */
        public string $mode,
        public Money $price,
        public Money $downPayment,
        public string $markupPct,
        public int $months,
        public int $payDay,
        /** Ustamali qarz */
        public Money $debt,
        public Money $monthly,
        /** Savdo kuni to'lanadigan summa: A rejimda 1-oylik, B rejimda boshlang'ich */
        public Money $dueToday,
        /** Jami tushum: boshlang'ich + ustamali qarz */
        public Money $revenue,
        public array $schedule,
    ) {}
}
