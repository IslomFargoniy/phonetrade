<?php

namespace App\Support;

/**
 * O'zbekiston telefon raqamlari: bazada `998901234567` ko'rinishida saqlanadi.
 */
final class Phone
{
    public const PATTERN = '/^998\d{9}$/';

    /**
     * Kiritilgan matnni `998XXXXXXXXX` ko'rinishiga keltiradi; noto'g'ri bo'lsa null.
     */
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (strlen($digits) === 9) {
            $digits = '998'.$digits;
        }

        return preg_match(self::PATTERN, $digits) === 1 ? $digits : null;
    }

    /**
     * `+998 90 123 45 67` ko'rinishida chiqaradi.
     */
    public static function format(string $phone): string
    {
        if (preg_match(self::PATTERN, $phone) !== 1) {
            return $phone;
        }

        return sprintf(
            '+%s %s %s %s %s',
            substr($phone, 0, 3),
            substr($phone, 3, 2),
            substr($phone, 5, 3),
            substr($phone, 8, 2),
            substr($phone, 10, 2),
        );
    }
}
