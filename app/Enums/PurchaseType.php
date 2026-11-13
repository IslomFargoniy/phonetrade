<?php

namespace App\Enums;

enum PurchaseType: string
{
    case Supplier = 'supplier';
    case Opening = 'opening';
    case TradeIn = 'trade_in';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'Yetkazib beruvchidan',
            self::Opening => "Boshlang'ich qoldiq",
            self::TradeIn => 'Mijozdan sotib olish',
        };
    }
}
