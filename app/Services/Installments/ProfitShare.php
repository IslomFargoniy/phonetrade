<?php

namespace App\Services\Installments;

use App\Support\Money;

/**
 * Nasiyada foyda pul tushgan sari tan olinadi.
 *
 * Shartnoma tuzilganda foyda ulushi bir marta hisoblanadi:
 *   ulush = (jami tushum − tannarx) / jami tushum
 * Keyin har to'lovning shu ulushi foyda bo'ladi. Oxirgi to'lovda qolgan hamma foyda
 * olinadi, shuning uchun to'lovlardan yig'ilgan foyda shartnoma foydasiga aniq teng chiqadi.
 */
final class ProfitShare
{
    private const SCALE = 10;

    /**
     * @return numeric-string
     */
    public static function ratio(Money $revenue, Money $cost): string
    {
        if (! $revenue->isPositive()) {
            return '0';
        }

        return bcdiv($revenue->minus($cost)->amount, $revenue->amount, self::SCALE);
    }

    /**
     * @param  numeric-string  $ratio
     */
    public static function forPayment(Money $payment, string $ratio, Money $totalProfit, Money $recognised, bool $isFinal): Money
    {
        if ($isFinal) {
            return $totalProfit->minus($recognised);
        }

        return Money::of(bcmul($payment->amount, $ratio, self::SCALE), $payment->currency);
    }
}
