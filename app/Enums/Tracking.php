<?php

namespace App\Enums;

enum Tracking: string
{
    /** Har dona alohida: IMEI, barcode, o'z tannarxi (telefon) */
    case Serial = 'serial';

    /** Miqdor bilan: bitta barcode, N dona (aksesuar) */
    case Quantity = 'quantity';

    public function label(): string
    {
        return match ($this) {
            self::Serial => 'Donalab (IMEI)',
            self::Quantity => 'Miqdor bilan',
        };
    }
}
