<?php

namespace App\Support;

use App\Enums\Currency;
use InvalidArgumentException;

/**
 * Pul summasi: valyuta bilan birga, 2 kasr xonagacha, float ishlatilmaydi.
 *
 * Barcha amallar bcmath orqali, shuning uchun 0.1 + 0.2 kabi xatolar bo'lmaydi.
 */
final readonly class Money
{
    private const SCALE = 2;

    /** Oraliq hisoblar uchun qo'shimcha aniqlik */
    private const WORK_SCALE = 10;

    /** @var numeric-string */
    public string $amount;

    /**
     * @param  numeric-string  $amount
     */
    private function __construct(string $amount, public Currency $currency)
    {
        $this->amount = $amount;
    }

    public static function of(string|int $amount, Currency $currency): self
    {
        return new self(self::round(self::number($amount)), $currency);
    }

    public static function zero(Currency $currency): self
    {
        return new self('0.00', $currency);
    }

    public static function uzs(string|int $amount): self
    {
        return self::of($amount, Currency::UZS);
    }

    public static function usd(string|int $amount): self
    {
        return self::of($amount, Currency::USD);
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(bcadd($this->amount, $other->amount, self::SCALE), $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(bcsub($this->amount, $other->amount, self::SCALE), $this->currency);
    }

    /**
     * Songa ko'paytiradi (masalan, miqdor yoki 1.2 ustama koeffitsiyenti).
     */
    public function times(string|int $factor): self
    {
        return new self(
            self::round(bcmul($this->amount, self::number($factor), self::WORK_SCALE)),
            $this->currency,
        );
    }

    /**
     * Foizini hisoblaydi: 500 000 ning 20% = 100 000.
     */
    public function percent(string|int $percent): self
    {
        return new self(
            self::round(bcdiv(bcmul($this->amount, self::number($percent), self::WORK_SCALE), '100', self::WORK_SCALE)),
            $this->currency,
        );
    }

    /**
     * Boshqa valyutaga o'tkazadi. Kurs — 1 dollar necha so'm.
     */
    public function convertTo(Currency $target, string|int $usdRate): self
    {
        if ($target === $this->currency) {
            return $this;
        }

        $rate = self::number($usdRate);

        if (bccomp($rate, '0', self::WORK_SCALE) <= 0) {
            throw new InvalidArgumentException("Kurs musbat bo'lishi kerak.");
        }

        $converted = $target === Currency::UZS
            ? bcmul($this->amount, $rate, self::WORK_SCALE)
            : bcdiv($this->amount, $rate, self::WORK_SCALE);

        return new self(self::round($converted), $target);
    }

    /**
     * Qadamga karrali qilib yuqoriga yaxlitlaydi: 333 333 → 340 000 (qadam 10 000).
     */
    public function roundUpTo(string|int $step): self
    {
        $step = self::number($step);

        if (bccomp($step, '0', self::SCALE) <= 0) {
            throw new InvalidArgumentException("Yaxlitlash qadami musbat bo'lishi kerak.");
        }

        $whole = bcdiv($this->amount, $step, 0);
        $base = bcmul($whole, $step, self::SCALE);

        if (bccomp($base, $this->amount, self::SCALE) < 0) {
            $base = bcadd($base, $step, self::SCALE);
        }

        return new self($base, $this->currency);
    }

    public function compare(self $other): int
    {
        $this->assertSameCurrency($other);

        return bccomp($this->amount, $other->amount, self::SCALE);
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->compare($other) === 0;
    }

    public function greaterThan(self $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function isZero(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) === 0;
    }

    public function isNegative(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) < 0;
    }

    public function isPositive(): bool
    {
        return bccomp($this->amount, '0', self::SCALE) > 0;
    }

    /**
     * Ekranga chiqarish uchun: `5 925 000 so'm`, `$ 460`, `$ 12.50`.
     */
    public function format(): string
    {
        [$whole, $fraction] = explode('.', ltrim($this->amount, '-'));
        $grouped = number_format((float) $whole, 0, '', ' ');
        $number = $fraction === '00' ? $grouped : "{$grouped}.{$fraction}";
        $sign = $this->isNegative() ? '−' : '';

        return $this->currency === Currency::USD
            ? "{$sign}$ {$number}"
            : "{$sign}{$number} so'm";
    }

    /**
     * Kiritilgan qiymat son ekanini tekshiradi.
     *
     * @return numeric-string
     */
    private static function number(string|int $value): string
    {
        $value = is_int($value) ? (string) $value : trim($value);

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Son kutilgan edi: [{$value}]");
        }

        return $value;
    }

    /**
     * Yarim birlikni yuqoriga qilib 2 xonagacha yaxlitlaydi.
     *
     * @param  numeric-string  $value
     * @return numeric-string
     */
    private static function round(string $value): string
    {
        $adjustment = bccomp($value, '0', self::WORK_SCALE) < 0 ? '-0.005' : '0.005';

        return bcadd($value, $adjustment, self::SCALE);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(sprintf(
                'Turli valyutadagi summalar ustida amal bajarib bo\'lmaydi: %s va %s.',
                $this->currency->value,
                $other->currency->value,
            ));
        }
    }
}
