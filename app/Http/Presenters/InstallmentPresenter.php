<?php

namespace App\Http\Presenters;

use App\Enums\PlanStatus;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\SaleItem;
use App\Services\Installments\InstallmentQuote;
use App\Support\Money;
use App\Support\Phone;
use Carbon\CarbonImmutable;

final class InstallmentPresenter
{
    /**
     * Kassa oynasidagi shartnoma hisobi.
     *
     * @return array<string, mixed>
     */
    public static function quote(InstallmentQuote $quote): array
    {
        $rows = [];

        if ($quote->mode === 'B') {
            $rows[] = ['no' => '—', 'date' => now()->format('d.m.Y'), 'note' => "Boshlang'ich, ustamasiz", 'amount_label' => $quote->downPayment->format()];
        }

        foreach ($quote->schedule as $index => $row) {
            $rows[] = [
                'no' => (string) $row['no'],
                'date' => $row['due_date']->format('d.m.Y'),
                'note' => match (true) {
                    $quote->mode === 'A' && $index === 0 => 'Savdo paytida',
                    $index === count($quote->schedule) - 1 && ! $row['amount']->equals($quote->monthly) => 'Oxirgi, yaxlitlash farqi',
                    default => 'Oylik',
                },
                'amount_label' => $row['amount']->format(),
            ];
        }

        return [
            'mode' => $quote->mode,
            'currency' => $quote->price->currency->value,
            'markup_pct' => StockPresenter::trimDecimal($quote->markupPct),
            'price_label' => $quote->price->format(),
            'debt_label' => $quote->debt->format(),
            'monthly_label' => $quote->monthly->format(),
            'due_today' => $quote->dueToday->amount,
            'due_today_label' => $quote->dueToday->format(),
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function plan(InstallmentPlan $plan): array
    {
        $currency = $plan->currency;
        $money = fn (string $amount) => Money::of($amount, $currency)->format();
        $today = CarbonImmutable::today();

        $next = $plan->schedule->first(
            fn (InstallmentScheduleItem $row) => Money::of($row->paid_amount, $currency)->lessThan(Money::of($row->amount, $currency)),
        );

        $state = match (true) {
            $plan->status !== PlanStatus::Active => $plan->status->value,
            $next === null => 'active',
            $next->due_date->isBefore($today) => 'late',
            $next->due_date->isSameDay($today) => 'today',
            default => 'upcoming',
        };

        $days = $next ? (int) abs($today->diffInDays($next->due_date)) : 0;

        return [
            'id' => $plan->id,
            'sale_id' => $plan->sale_id,
            'sale_number' => $plan->sale->number,
            'customer_id' => $plan->customer_id,
            'customer' => $plan->customer->full_name,
            'customer_phone' => Phone::format($plan->customer->phone),
            'customer_phone_raw' => $plan->customer->phone,
            'product' => $plan->sale->items->map(fn (SaleItem $item) => $item->name)->join(', '),
            'months' => $plan->months,
            'mode' => $plan->mode,
            'currency' => $currency->value,
            'status' => $plan->status->value,
            'state' => $state,
            'state_label' => match ($state) {
                'late' => "{$days} kun kechikkan",
                'today' => 'Bugun',
                'upcoming' => "{$days} kundan keyin",
                'closed' => 'Yopilgan',
                'cancelled' => 'Bekor qilingan',
                default => 'Faol',
            },
            'next_date' => $next?->due_date->format('d.m.Y'),
            'next_amount' => $next ? Money::of($next->amount, $currency)->minus(Money::of($next->paid_amount, $currency))->amount : null,
            'next_amount_label' => $next ? Money::of($next->amount, $currency)->minus(Money::of($next->paid_amount, $currency))->format() : null,
            'paid_label' => $money($plan->paid_total),
            'remaining' => $plan->remaining()->amount,
            'remaining_label' => $plan->remaining()->format(),
            'total_label' => $money($plan->total_revenue),
            'down_payment_label' => Money::of($plan->down_payment, $currency)->isPositive() ? $money($plan->down_payment) : null,
            'markup_pct' => StockPresenter::trimDecimal($plan->markup_pct),
            'created_at' => $plan->created_at?->format('d.m.Y'),
            'schedule' => $plan->schedule->map(fn (InstallmentScheduleItem $row) => [
                'no' => $row->no,
                'date' => $row->due_date->format('d.m.Y'),
                'amount_label' => $money($row->amount),
                'paid_label' => $money($row->paid_amount),
                'state' => match (true) {
                    ! Money::of($row->paid_amount, $currency)->lessThan(Money::of($row->amount, $currency)) => 'paid',
                    $plan->status === PlanStatus::Active && $row->due_date->isBefore($today) => 'late',
                    Money::of($row->paid_amount, $currency)->isPositive() => 'partial',
                    default => 'pending',
                },
            ]),
            'payments' => $plan->payments->sortByDesc('paid_at')->values()->map(fn (InstallmentPayment $payment) => [
                'id' => $payment->id,
                'date' => $payment->paid_at->format('d.m.Y H:i'),
                'amount_label' => $money($payment->amount),
                'is_initial' => $payment->is_initial,
            ]),
        ];
    }
}
