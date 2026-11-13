<?php

namespace App\Enums;

enum TransferStatus: string
{
    case InTransit = 'in_transit';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::InTransit => "Yo'lda",
            self::Received => 'Qabul qilingan',
        };
    }
}
