<?php

namespace Tests\Unit;

use App\Enums\Currency;
use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_decimal_arithmetic_has_no_float_errors(): void
    {
        $sum = Money::usd('0.10')->plus(Money::usd('0.20'));

        $this->assertSame('0.30', $sum->amount);
        $this->assertSame('99.99', Money::usd('100')->minus(Money::usd('0.01'))->amount);
    }

    public function test_percent_and_times(): void
    {
        // 500 000 so'm + 20% ustama = 600 000
        $price = Money::uzs(500000);

        $this->assertSame('100000.00', $price->percent(20)->amount);
        $this->assertSame('600000.00', $price->plus($price->percent(20))->amount);
        $this->assertSame('120000.00', Money::uzs(60000)->times(2)->amount);
    }

    public function test_conversion_uses_the_given_rate(): void
    {
        // Texnik rejadagi misol: $460 × 12 750 = 5 865 000 so'm
        $this->assertSame('5865000.00', Money::usd(460)->convertTo(Currency::UZS, '12750')->amount);
        $this->assertSame('460.00', Money::uzs(5865000)->convertTo(Currency::USD, '12750')->amount);
        $this->assertSame('78.43', Money::uzs(1000000)->convertTo(Currency::USD, '12750')->amount);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function roundingCases(): array
    {
        return [
            '1 000 000 / 3 → 340 000' => ['333333.33', '10000', '340000.00'],
            'already a multiple' => ['160000', '10000', '160000.00'],
            'usd step 10' => ['153.34', '10', '160.00'],
            'just above a multiple' => ['160000.01', '10000', '170000.00'],
        ];
    }

    #[DataProvider('roundingCases')]
    public function test_round_up_to_step(string $amount, string $step, string $expected): void
    {
        $this->assertSame($expected, Money::uzs($amount)->roundUpTo($step)->amount);
    }

    public function test_half_is_rounded_away_from_zero(): void
    {
        $this->assertSame('0.01', Money::usd('0.005')->amount);
        $this->assertSame('-0.01', Money::usd('-0.005')->amount);
        $this->assertSame('0.00', Money::usd('0.004')->amount);
    }

    public function test_different_currencies_cannot_be_mixed(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::uzs(1000)->plus(Money::usd(1));
    }

    public function test_comparison(): void
    {
        $this->assertTrue(Money::uzs(100)->greaterThan(Money::uzs(99)));
        $this->assertTrue(Money::uzs(100)->equals(Money::uzs('100.00')));
        $this->assertFalse(Money::uzs(100)->equals(Money::usd(100)));
        $this->assertTrue(Money::zero(Currency::USD)->isZero());
        $this->assertTrue(Money::uzs(-5)->isNegative());
    }

    public function test_format(): void
    {
        $this->assertSame("5 925 000 so'm", Money::uzs(5925000)->format());
        $this->assertSame('$ 460', Money::usd(460)->format());
        $this->assertSame('$ 12.50', Money::usd('12.5')->format());
        $this->assertSame("−150 000 so'm", Money::uzs(-150000)->format());
    }
}
