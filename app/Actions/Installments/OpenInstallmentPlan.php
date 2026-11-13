<?php

namespace App\Actions\Installments;

use App\Enums\PlanStatus;
use App\Models\Branch;
use App\Models\InstallmentPlan;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Installments\InstallmentQuote;
use App\Services\Installments\ProfitShare;
use App\Services\Sales\CartCalculator;
use App\Support\Money;

/**
 * Savdo yopilganda nasiya shartnomasini ochadi: jadval, foyda ulushi va savdo
 * kuni to'langan birinchi to'lov. CloseSale tranzaksiyasi ichida chaqiriladi.
 */
class OpenInstallmentPlan
{
    public function __construct(private readonly CartCalculator $cart) {}

    public function handle(Sale $sale, Branch $branch, User $user, int $customerId, InstallmentQuote $quote, ?string $rate): InstallmentPlan
    {
        $currency = $quote->price->currency;

        // Tannarx savdo qatorlarida savdo valyutasida saqlangan; shartnoma valyutasiga keltiriladi.
        $cost = SaleItem::query()->where('sale_id', $sale->id)->get()->reduce(
            fn (Money $carry, SaleItem $item) => $carry->plus(
                $this->cart->convert(Money::of($item->cost_snapshot, $sale->currency), $currency, $rate),
            ),
            Money::zero($currency),
        );

        $totalProfit = $quote->revenue->minus($cost);
        $ratio = ProfitShare::ratio($quote->revenue, $cost);

        $plan = InstallmentPlan::query()->create([
            'sale_id' => $sale->id,
            'customer_id' => $customerId,
            'branch_id' => $branch->id,
            'currency' => $currency,
            'mode' => $quote->mode,
            'price' => $quote->price->amount,
            'down_payment' => $quote->downPayment->amount,
            'markup_pct' => $quote->markupPct,
            'months' => $quote->months,
            'pay_day' => $quote->payDay,
            'total_debt' => $quote->debt->amount,
            'total_revenue' => $quote->revenue->amount,
            'total_profit' => $totalProfit->amount,
            'profit_ratio' => $ratio,
            'paid_total' => $quote->dueToday->amount,
            'status' => PlanStatus::Active,
        ]);

        foreach ($quote->schedule as $index => $row) {
            $plan->schedule()->create([
                'no' => $row['no'],
                'due_date' => $row['due_date']->toDateString(),
                'amount' => $row['amount']->amount,
                // A rejimda 1-oylik savdo kuni to'lanadi; B rejimda boshlang'ich jadvalga kirmaydi.
                'paid_amount' => $quote->mode === 'A' && $index === 0 ? $row['amount']->amount : '0',
            ]);
        }

        $plan->payments()->create([
            'user_id' => $user->id,
            'amount' => $quote->dueToday->amount,
            'profit_amount' => ProfitShare::forPayment($quote->dueToday, $ratio, $totalProfit, Money::zero($currency), false)->amount,
            'is_initial' => true,
            'paid_at' => now(),
        ]);

        return $plan;
    }
}
