<?php

namespace App\Enums;

enum SaleType: string
{
    case Full = 'full';
    case Installment = 'installment';

    public function label(): string
    {
        return match ($this) {
            self::Full => "To'liq to'lov",
            self::Installment => 'Muddatli',
        };
    }
}
