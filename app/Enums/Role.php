<?php

namespace App\Enums;

enum Role: string
{
    /** Platforma egasi: do'kon ochadi va bloklaydi. Hech bir do'konga tegishli emas. */
    case Superadmin = 'superadmin';

    /** Do'kon egasi: o'z do'konida hamma narsaga ruxsati bor. */
    case Owner = 'owner';

    /** Sotuvchi: faqat biriktirilgan filiallarda ishlaydi, tannarx va foydani ko'rmaydi. */
    case Seller = 'seller';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Owner => 'Egasi',
            self::Seller => 'Sotuvchi',
        };
    }
}
