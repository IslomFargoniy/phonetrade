<?php

namespace App\Services\Installments;

use App\Enums\Currency;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\InstallmentRate;
use App\Models\Tenant;
use App\Services\Sales\CartCalculator;
use App\Services\Sales\CartLine;
use App\Services\Sales\CartTotals;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Savat va tanlangan shartlardan nasiya hisobini tuzadi: filial ustamasi va
 * do'kon yaxlitlash qadami shu yerda qo'llanadi.
 */
class InstallmentPlanner
{
    public function __construct(
        private readonly InstallmentCalculator $calculator,
        private readonly CartCalculator $cart,
    ) {}

    public function quote(Branch $branch, CartTotals $totals, InstallmentTerms $terms, ?string $rate): InstallmentQuote
    {
        $markup = InstallmentRate::query()
            ->where('branch_id', $branch->id)
            ->where('months', $terms->months)
            ->value('markup_pct');

        if ($markup === null) {
            throw new BusinessRuleException('MONTHS_INVALID', 'Bu muddat uchun ustama belgilanmagan.', 'months');
        }

        $currency = $this->planCurrency($totals, $terms);
        $tenant = Tenant::query()->findOrFail($branch->tenant_id);
        $step = $currency === Currency::USD
            ? $tenant->setting('round_step_usd', Tenant::DEFAULT_ROUND_STEP_USD)
            : $tenant->setting('round_step_uzs', Tenant::DEFAULT_ROUND_STEP_UZS);

        return $this->calculator->quote(
            price: $this->cart->convert($totals->total, $currency, $rate),
            downPayment: Money::of($terms->downPayment !== '' ? $terms->downPayment : '0', $currency),
            markupPct: (string) $markup,
            months: $terms->months,
            payDay: $terms->payDay,
            roundStep: (string) $step,
            saleDate: CarbonImmutable::now(),
        );
    }

    /**
     * Savatda aralash valyutali tovar bo'lsa, shartnoma faqat so'mda tuziladi.
     */
    private function planCurrency(CartTotals $totals, InstallmentTerms $terms): Currency
    {
        $mixed = collect($totals->lines)->contains(fn (CartLine $line) => $line->price->currency !== $totals->currency);

        return $mixed ? Currency::UZS : $terms->currency;
    }
}
