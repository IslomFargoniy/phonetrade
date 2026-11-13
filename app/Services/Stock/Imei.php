<?php

namespace App\Services\Stock;

/**
 * IMEI tekshiruvi: 15 raqam, oxirgisi Luhn nazorat raqami.
 */
final class Imei
{
    public static function isValid(string $imei): bool
    {
        if (preg_match('/^\d{15}$/', $imei) !== 1) {
            return false;
        }

        $sum = 0;

        foreach (str_split(strrev($imei)) as $position => $char) {
            $digit = (int) $char;

            if ($position % 2 === 1) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    public static function normalize(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
