<?php

namespace Tests\Unit;

use App\Exceptions\BusinessRuleException;
use App\Services\Installments\InstallmentCalculator;
use App\Services\Installments\InstallmentQuote;
use App\Services\Installments\ProfitShare;
use App\Support\Money;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Raqamlar texnik rejadagi misollardan olingan.
 */
class InstallmentCalculatorTest extends TestCase
{
    private function quote(string $price, string $down, string $pct, int $months, int $payDay = 13, string $saleDate = '2026-11-05', string $step = '10000'): InstallmentQuote
    {
        return (new InstallmentCalculator)->quote(
            Money::uzs($price), Money::uzs($down), $pct, $months, $payDay, $step, CarbonImmutable::parse($saleDate),
        );
    }

    /**
     * @return list<string>
     */
    private function amounts(InstallmentQuote $quote): array
    {
        return array_map(fn (array $row) => $row['amount']->amount, $quote->schedule);
    }

    /**
     * @return list<string>
     */
    private function dates(InstallmentQuote $quote): array
    {
        return array_map(fn (array $row) => $row['due_date']->toDateString(), $quote->schedule);
    }

    public function test_mode_a_marks_up_the_whole_price_and_first_payment_is_at_sale(): void
    {
        // 500 000, 3 oy, 20%, to'lov kuni 13 → jami 600 000; 200 000 × 3
        $quote = $this->quote('500000', '0', '20', 3);

        $this->assertSame('A', $quote->mode);
        $this->assertSame('600000.00', $quote->debt->amount);
        $this->assertSame('600000.00', $quote->revenue->amount);
        $this->assertSame('200000.00', $quote->dueToday->amount);
        $this->assertSame(['200000.00', '200000.00', '200000.00'], $this->amounts($quote));
        $this->assertSame(['2026-11-05', '2026-12-13', '2027-01-13'], $this->dates($quote));
    }

    public function test_mode_b_marks_up_only_the_remainder_and_starts_next_month(): void
    {
        // 500 000, 100 000 boshlang'ich, 3 oy, 20% → (500 000 − 100 000) × 1,2 = 480 000; 160 000 × 3
        $quote = $this->quote('500000', '100000', '20', 3);

        $this->assertSame('B', $quote->mode);
        $this->assertSame('480000.00', $quote->debt->amount);
        $this->assertSame('580000.00', $quote->revenue->amount);
        $this->assertSame('100000.00', $quote->dueToday->amount);
        $this->assertSame(['160000.00', '160000.00', '160000.00'], $this->amounts($quote));
        $this->assertSame(['2026-12-13', '2027-01-13', '2027-02-13'], $this->dates($quote));
    }

    public function test_monthly_is_rounded_up_and_the_last_month_absorbs_the_difference(): void
    {
        // 1 000 000 qarz, 3 oy → 340 000, 340 000, 320 000 (jami oshmaydi)
        $quote = $this->quote('1000000', '0', '0', 3);

        $this->assertSame('340000.00', $quote->monthly->amount);
        $this->assertSame(['340000.00', '340000.00', '320000.00'], $this->amounts($quote));
        $this->assertSame('340000.00', $quote->dueToday->amount);
    }

    public function test_dollar_contract_rounds_to_ten_dollars(): void
    {
        $quote = (new InstallmentCalculator)->quote(
            Money::usd(460), Money::usd(0), '55', 12, 13, '10', CarbonImmutable::parse('2026-11-05'),
        );

        // 460 × 1,55 = 713; 713 / 12 = 59,42 → 60; oxirgi: 713 − 60 × 11 = 53
        $this->assertSame('713.00', $quote->debt->amount);
        $this->assertSame('60.00', $quote->monthly->amount);
        $this->assertSame('53.00', $quote->schedule[11]['amount']->amount);
    }

    public function test_schedule_always_sums_to_the_debt(): void
    {
        foreach ([[3, '20'], [6, '35'], [9, '45'], [12, '55']] as [$months, $pct]) {
            foreach (['0', '700000'] as $down) {
                $quote = $this->quote('5900000', $down, $pct, $months);
                $sum = array_reduce($quote->schedule, fn (Money $carry, array $row) => $carry->plus($row['amount']), Money::uzs(0));

                $this->assertSame($quote->debt->amount, $sum->amount, "{$months} oy, boshlang'ich {$down}");
                $this->assertCount($months, $quote->schedule);
            }
        }
    }

    public function test_pay_day_does_not_have_to_be_far_from_the_sale_date(): void
    {
        // 27-sanada sotilgan, to'lov kuni 1: keyingi oyning 1-sanasi — cheklanmaydi.
        $quote = $this->quote('500000', '100000', '20', 3, 1, '2026-11-27');

        $this->assertSame(['2026-12-01', '2027-01-01', '2027-02-01'], $this->dates($quote));
    }

    public function test_invalid_terms_are_rejected(): void
    {
        $codes = [];

        foreach ([
            fn () => $this->quote('500000', '0', '20', 3, 29),
            fn () => $this->quote('500000', '500000', '20', 3),
            fn () => $this->quote('10000', '0', '0', 3),
        ] as $attempt) {
            try {
                $attempt();
            } catch (BusinessRuleException $exception) {
                $codes[] = $exception->errorCode;
            }
        }

        $this->assertSame(['PAY_DAY_INVALID', 'DOWN_PAYMENT_INVALID', 'INSTALLMENT_TOO_SMALL'], $codes);
    }

    public function test_profit_share_matches_the_plan_examples(): void
    {
        // A: tushum 600 000, tannarx 450 000 → ulush 25%; 200 000 → 50 000
        $ratio = ProfitShare::ratio(Money::uzs(600000), Money::uzs(450000));
        $profit = ProfitShare::forPayment(Money::uzs(200000), $ratio, Money::uzs(150000), Money::uzs(0), false);

        $this->assertSame('0.2500000000', $ratio);
        $this->assertSame('50000.00', $profit->amount);

        // B: tushum 580 000, tannarx 450 000 → 22,41%; 100 000 → 22 413,79; 160 000 → 35 862,07
        $ratio = ProfitShare::ratio(Money::uzs(580000), Money::uzs(450000));
        $total = Money::uzs(130000);
        $first = ProfitShare::forPayment(Money::uzs(100000), $ratio, $total, Money::uzs(0), false);
        $second = ProfitShare::forPayment(Money::uzs(160000), $ratio, $total, $first, false);
        $third = ProfitShare::forPayment(Money::uzs(160000), $ratio, $total, $first->plus($second), false);
        $last = ProfitShare::forPayment(Money::uzs(160000), $ratio, $total, $first->plus($second)->plus($third), true);

        $this->assertSame('22413.79', $first->amount);
        $this->assertSame('35862.07', $second->amount);

        // To'lovlardan yig'ilgan foyda shartnoma foydasiga aniq teng.
        $this->assertSame('130000.00', $first->plus($second)->plus($third)->plus($last)->amount);
    }
}
