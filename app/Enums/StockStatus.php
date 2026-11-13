<?php

namespace App\Enums;

enum StockStatus: string
{
    case InStock = 'in_stock';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Returned = 'returned';
    case InTransit = 'in_transit';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'Omborda',
            self::Reserved => 'Band',
            self::Sold => 'Sotilgan',
            self::Returned => 'Qaytgan',
            self::InTransit => "Yo'lda",
        };
    }
}
