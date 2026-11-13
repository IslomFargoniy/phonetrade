<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Completed = 'completed';
    case PartiallyReturned = 'partially_returned';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Yopilgan',
            self::PartiallyReturned => 'Qisman qaytarilgan',
            self::Returned => 'Qaytarilgan',
        };
    }
}
