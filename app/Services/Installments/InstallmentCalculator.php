<?php

namespace App\Services\Installments;

use App\Exceptions\BusinessRuleException;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Muddatli to'lov hisobi. Ustama bir marta, shartnoma tuzilganda qo'shiladi:
 * keyin jarima ham, qo'shimcha foiz ham yo'q.
 *
 * A rejim (boshlang'ichsiz): ustama butun narxga; 1-oylik savdo kuni to'lanadi,
 *   qolganlari keyingi oylarning tanlangan sanasida.
 * B rejim (boshlang'ich bilan): ustama faqat qoldiqqa; N ta oylik keyingi oydan.
 *
 *   qarz   = (narx − boshlang'ich) × (1 + ustama%)
 *   oylik  = qarz / N, qadamga yuqoriga yaxlitlanadi
 *   oxirgi = qarz − oylik × (N − 1)
 */
class InstallmentCalculator
{
    public const MIN_PAY_DAY = 1;

    public const MAX_PAY_DAY = 28;

    public function quote(
        Money $price,
        Money $downPayment,
        string $markupPct,
        int $months,
        int $payDay,
        string $roundStep,
        CarbonImmutable $saleDate,
    ): InstallmentQuote {
        if ($payDay < self::MIN_PAY_DAY || $payDay > self::MAX_PAY_DAY) {
            throw new BusinessRuleException('PAY_DAY_INVALID', "To'lov kuni 1 dan 28 gacha bo'lishi kerak.", 'pay_day');
        }

        if ($months < 1) {
            throw new BusinessRuleException('MONTHS_INVALID', "Muddat noto'g'ri.", 'months');
        }

        if ($downPayment->isNegative() || ! $downPayment->lessThan($price)) {
            throw new BusinessRuleException(
                'DOWN_PAYMENT_INVALID',
                "Boshlang'ich to'lov narxdan kichik bo'lishi kerak.",
                'down_payment',
            );
        }

        $mode = $downPayment->isPositive() ? 'B' : 'A';
        $base = $price->minus($downPayment);
        $debt = $base->plus($base->percent($markupPct));

        $monthly = Money::of(bcdiv($debt->amount, (string) $months, 10), $debt->currency)->roundUpTo($roundStep);
        $last = $debt->minus($monthly->times($months - 1));

        // Yaxlitlash oylikni oshiradi; qarz juda kichik bo'lsa oxirgi oyga hech narsa qolmaydi.
        if (! $last->isPositive()) {
            throw new BusinessRuleException(
                'INSTALLMENT_TOO_SMALL',
                'Qarz bu muddatga bo\'lish uchun juda kichik. Qisqaroq muddat tanlang.',
                'months',
            );
        }

        $schedule = [];

        for ($no = 1; $no <= $months; $no++) {
            // A rejimda 1-oylik savdo kunining o'zida, shuning uchun sanalar bir oy oldin boshlanadi.
            $offset = $mode === 'A' ? $no - 1 : $no;

            $schedule[] = [
                'no' => $no,
                'due_date' => $offset === 0
                    ? $saleDate->startOfDay()
                    : $saleDate->startOfMonth()->addMonthsNoOverflow($offset)->setDay($payDay),
                'amount' => $no === $months ? $last : $monthly,
            ];
        }

        return new InstallmentQuote(
            mode: $mode,
            price: $price,
            downPayment: $downPayment,
            markupPct: $markupPct,
            months: $months,
            payDay: $payDay,
            debt: $debt,
            monthly: $monthly,
            dueToday: $mode === 'A' ? $schedule[0]['amount'] : $downPayment,
            revenue: $downPayment->plus($debt),
            schedule: $schedule,
        );
    }
}
