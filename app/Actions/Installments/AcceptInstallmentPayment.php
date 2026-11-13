<?php

namespace App\Actions\Installments;

use App\Enums\CashAccountType;
use App\Enums\PlanStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\User;
use App\Services\Cash\CashService;
use App\Services\Installments\ProfitShare;
use App\Services\Rates\CurrencyRateService;
use App\Services\Sales\CartCalculator;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Nasiya to'lovini qabul qiladi.
 *
 * Mijoz istalgan summani to'laydi: pul eng eski to'lanmagan oylikdan boshlab
 * taqsimlanadi, ortiqchasi keyingi oyliklarni oldindan yopadi. Jadvaldagi summalar
 * o'zgarmaydi, ustama kamaymaydi. Dollarli qarz so'mda to'lansa, to'lov kunidagi
 * kurs bilan hisoblanadi.
 */
class AcceptInstallmentPayment
{
    public function __construct(
        private readonly CashService $cash,
        private readonly CurrencyRateService $rates,
        private readonly CartCalculator $converter,
    ) {}

    public function handle(InstallmentPlan $plan, Branch $branch, User $user, Money $paid, CashAccountType $method): InstallmentPayment
    {
        $rate = $this->rates->current()?->usd_uzs;

        return DB::transaction(function () use ($plan, $branch, $user, $paid, $method, $rate): InstallmentPayment {
            $plan = InstallmentPlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();

            if ($plan->status !== PlanStatus::Active) {
                throw new BusinessRuleException('PLAN_NOT_ACTIVE', 'Bu shartnoma yopilgan yoki bekor qilingan.', 'amount');
            }

            if (! $paid->isPositive()) {
                throw new BusinessRuleException('AMOUNT_INVALID', "Summa noldan katta bo'lishi kerak.", 'amount');
            }

            $rows = InstallmentScheduleItem::query()
                ->where('installment_plan_id', $plan->id)
                ->orderBy('no')
                ->lockForUpdate()
                ->get();

            $amount = $this->converter->convert($paid, $plan->currency, $rate);
            $remaining = $rows->reduce(
                fn (Money $carry, InstallmentScheduleItem $row) => $carry
                    ->plus(Money::of($row->amount, $plan->currency))
                    ->minus(Money::of($row->paid_amount, $plan->currency)),
                Money::zero($plan->currency),
            );

            if ($amount->greaterThan($remaining)) {
                throw new BusinessRuleException(
                    'PAYMENT_EXCEEDS_DEBT',
                    sprintf('Summa qolgan qarzdan katta. Qolgan qarz: %s.', $remaining->format()),
                    'amount',
                );
            }

            // Eng eski to'lanmagan oylikdan boshlab taqsimlanadi.
            $left = $amount;

            foreach ($rows as $row) {
                if (! $left->isPositive()) {
                    break;
                }

                $open = Money::of($row->amount, $plan->currency)->minus(Money::of($row->paid_amount, $plan->currency));

                if (! $open->isPositive()) {
                    continue;
                }

                $part = $left->lessThan($open) ? $left : $open;
                $row->update(['paid_amount' => Money::of($row->paid_amount, $plan->currency)->plus($part)->amount]);
                $left = $left->minus($part);
            }

            $isFinal = $amount->equals($remaining);
            $recognised = Money::of((string) $plan->payments()->sum('profit_amount'), $plan->currency);

            $payment = $plan->payments()->create([
                'user_id' => $user->id,
                'amount' => $amount->amount,
                'profit_amount' => ProfitShare::forPayment(
                    $amount,
                    $plan->profit_ratio,
                    Money::of($plan->total_profit, $plan->currency),
                    $recognised,
                    $isFinal,
                )->amount,
                'rate' => $rate,
                'is_initial' => false,
                'paid_at' => now(),
            ]);

            $plan->update([
                'paid_total' => Money::of($plan->paid_total, $plan->currency)->plus($amount)->amount,
                'status' => $isFinal ? PlanStatus::Closed : PlanStatus::Active,
            ]);

            $account = CashAccount::query()->where('branch_id', $branch->id)->where('type', $method)->firstOrFail();
            $this->cash->deposit($account, $paid, 'installment_payment', $payment, $rate);

            Audit::log('installment.payment', $plan, null, ['amount' => $amount->amount, 'closed' => $isFinal]);

            return $payment;
        });
    }
}
