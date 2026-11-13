<?php

namespace App\Services\Sales;

use App\Http\Presenters\InstallmentPresenter;
use App\Http\Presenters\SalePresenter;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\StockUnit;
use App\Services\Installments\InstallmentPlanner;
use App\Services\Installments\InstallmentTerms;
use App\Services\Rates\CurrencyRateService;

/**
 * Savatni saqlamasdan hisoblaydi: ekranda ko'rsatiladigan raqamlar savdoni
 * yopishdagi hisob bilan bir xil chiqadi.
 */
class CartPreview
{
    public function __construct(
        private readonly CartCalculator $calculator,
        private readonly CurrencyRateService $rates,
        private readonly InstallmentPlanner $planner,
    ) {}

    /**
     * @param  list<array{stock_unit_id: int|null, variant_id: int|null, qty: int, discount: string}>  $items
     * @return array<string, mixed>
     */
    public function for(Branch $branch, array $items, ?InstallmentTerms $terms): array
    {
        $rate = $this->rates->current()?->usd_uzs;
        $loaded = [];

        foreach ($items as $item) {
            $loaded[] = [
                'unit' => $item['stock_unit_id'] !== null
                    ? StockUnit::query()->with('product')->where('branch_id', $branch->id)->find($item['stock_unit_id'])
                    : null,
                'variant' => $item['variant_id'] !== null
                    ? ProductVariant::query()->with('product')->find($item['variant_id'])
                    : null,
                'qty' => $item['qty'],
                'discount' => $item['discount'],
            ];
        }

        $totals = $this->calculator->calculate($loaded, $rate);

        return [
            ...SalePresenter::totals($totals, $rate),
            'installment' => $terms !== null
                ? InstallmentPresenter::quote($this->planner->quote($branch, $totals, $terms, $rate))
                : null,
        ];
    }
}
