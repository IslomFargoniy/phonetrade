<?php

namespace App\Enums;

enum PlanStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Faol',
            self::Closed => 'Yopilgan',
            self::Cancelled => 'Bekor qilingan',
        };
    }
}
