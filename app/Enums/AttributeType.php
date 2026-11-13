<?php

namespace App\Enums;

enum AttributeType: string
{
    case Select = 'select';
    case Number = 'number';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Select => "Ro'yxatdan",
            self::Number => 'Son',
            self::Text => 'Matn',
        };
    }
}
