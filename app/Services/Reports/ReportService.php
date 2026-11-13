<?php

namespace App\Services\Reports;

use App\Enums\CashDirection;
use App\Enums\Currency;
use App\Enums\PlanStatus;
use App\Enums\SaleType;
use App\Enums\StockStatus;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Expense;
use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\StockBatch;
use App\Models\StockUnit;
use App\Services\Rates\CurrencyRateService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hisobotlar. Barcha yig'indilar so'mda: dollardagi summa o'sha hujjatda saqlangan
 * kurs bilan o'tkaziladi (savdo kursi, to'lov kursi, kirim kursi), shuning uchun
 * kurs keyin o'zgarsa ham eski davr hisoboti o'zgarmaydi.
 *
 * Yalpi foyda = to'liq savdolar foydasi + nasiya to'lovlaridagi foyda ulushi − qaytarilgan foyda.
 */
class ReportService
{
    public function __construct(private readonly CurrencyRateService $rates) {}

    /**
     * @return array{sales_count: int, revenue: Money, discounts: Money, gross_profit: Money, expenses: Money, net_profit: Money, installment_income: Money, installment_payments: int, active_debt: Money, active_plans: int}
     */
    public function summary(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array
    {
        $sales = $this->sales($from, $to, $branchId)->with('items')->get();

        $revenue = Money::uzs(0);
        $discounts = Money::uzs(0);
        $profit = Money::uzs(0);

        foreach ($sales as $sale) {
            $revenue = $revenue->plus($this->uzs($sale->total, $sale->currency, $sale->rate));
            $discounts = $discounts->plus($this->uzs($sale->discount, $sale->currency, $sale->rate));

            // Nasiyali savdo foydasi to'lovlar bilan keladi, bu yerda qo'shilmaydi.
            if ($sale->type === SaleType::Full) {
                foreach ($sale->items as $item) {
                    $profit = $profit->plus($this->uzs($item->profit, $sale->currency, $sale->rate));
                }
            }
        }

        $payments = InstallmentPayment::query()
            ->with('plan')
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($branchId, fn ($query) => $query->whereHas('plan', fn ($plan) => $plan->where('branch_id', $branchId)))
            ->get();

        $installmentIncome = Money::uzs(0);

        foreach ($payments as $payment) {
            $installmentIncome = $installmentIncome->plus($this->uzs($payment->amount, $payment->plan->currency, $payment->rate));
            $profit = $profit->plus($this->uzs($payment->profit_amount, $payment->plan->currency, $payment->rate));
        }

        $returns = SaleReturn::query()
            ->with('sale')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($branchId, fn ($query) => $query->whereHas('sale', fn ($sale) => $sale->where('branch_id', $branchId)))
            ->get();

        foreach ($returns as $return) {
            $currency = Currency::from($return->currency ?? $return->sale->currency->value);
            $profit = $profit->minus($this->uzs($return->profit_reversed, $currency, $return->sale->rate));
        }

        $expenses = Expense::query()
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get()
            ->reduce(fn (Money $carry, Expense $expense) => $carry->plus($this->uzs($expense->amount, $expense->currency, $expense->rate)), Money::uzs(0));

        $plans = InstallmentPlan::query()
            ->where('status', PlanStatus::Active)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get();

        return [
            'sales_count' => $sales->count(),
            'revenue' => $revenue,
            'discounts' => $discounts,
            'gross_profit' => $profit,
            'expenses' => $expenses,
            'net_profit' => $profit->minus($expenses),
            'installment_income' => $installmentIncome,
            'installment_payments' => $payments->count(),
            'active_debt' => $plans->reduce(
                fn (Money $carry, InstallmentPlan $plan) => $carry->plus($this->uzs($plan->remaining()->amount, $plan->currency, null)),
                Money::uzs(0),
            ),
            'active_plans' => $plans->count(),
        ];
    }

    /**
     * Kutilayotgan foyda: faol nasiyalar jadval bo'yicha to'lansa, qaysi oyda qancha foyda tushadi.
     * Muddati o'tgan oyliklar joriy oyga qo'shiladi.
     *
     * @return list<array{month: string, label: string, profit: Money}>
     */
    public function expectedProfit(int $months = 6, ?int $branchId = null): array
    {
        $start = CarbonImmutable::today()->startOfMonth();
        $buckets = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->addMonthsNoOverflow($i);
            $buckets[$month->format('Y-m')] = ['month' => $month->format('Y-m'), 'label' => self::MONTHS[$month->month - 1], 'profit' => Money::uzs(0)];
        }

        $rows = InstallmentScheduleItem::query()
            ->with('plan')
            ->whereColumn('paid_amount', '<', 'amount')
            ->whereHas('plan', fn ($plan) => $plan
                ->where('status', PlanStatus::Active)
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId)))
            ->get();

        foreach ($rows as $row) {
            $plan = $row->plan;
            $key = $row->due_date->lessThan($start) ? $start->format('Y-m') : $row->due_date->format('Y-m');

            if (! isset($buckets[$key])) {
                continue;
            }

            $open = Money::of($row->amount, $plan->currency)->minus(Money::of($row->paid_amount, $plan->currency));
            $share = Money::of(bcmul($open->amount, $plan->profit_ratio, 10), $plan->currency);

            $buckets[$key]['profit'] = $buckets[$key]['profit']->plus($this->uzs($share->amount, $plan->currency, null));
        }

        return array_values($buckets);
    }

    /**
     * @return list<array{name: string, sales: int, revenue: Money, profit: Money}>
     */
    public function salesBySeller(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array
    {
        $rows = [];

        foreach ($this->sales($from, $to, $branchId)->with(['items', 'user:id,name'])->get() as $sale) {
            $name = $sale->user->name ?? '—';
            $rows[$name] ??= ['name' => $name, 'sales' => 0, 'revenue' => Money::uzs(0), 'profit' => Money::uzs(0)];
            $rows[$name]['sales']++;
            $rows[$name]['revenue'] = $rows[$name]['revenue']->plus($this->uzs($sale->total, $sale->currency, $sale->rate));

            if ($sale->type === SaleType::Full) {
                foreach ($sale->items as $item) {
                    $rows[$name]['profit'] = $rows[$name]['profit']->plus($this->uzs($item->profit, $sale->currency, $sale->rate));
                }
            }
        }

        usort($rows, fn (array $a, array $b) => $b['revenue']->compare($a['revenue']));

        return $rows;
    }

    /**
     * @return list<array{name: string, qty: int, revenue: Money, profit: Money}>
     */
    public function salesByProduct(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array
    {
        $rows = [];

        $items = SaleItem::query()
            ->with('sale')
            ->whereHas('sale', fn ($sale) => $sale
                ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId)))
            ->get();

        foreach ($items as $item) {
            $sale = $item->sale;
            $rows[$item->name] ??= ['name' => $item->name, 'qty' => 0, 'revenue' => Money::uzs(0), 'profit' => Money::uzs(0)];
            $rows[$item->name]['qty'] += $item->qty - $item->returned_qty;
            $rows[$item->name]['revenue'] = $rows[$item->name]['revenue']->plus($this->uzs($item->final_price, $sale->currency, $sale->rate));

            if ($sale->type === SaleType::Full) {
                $rows[$item->name]['profit'] = $rows[$item->name]['profit']->plus($this->uzs($item->profit, $sale->currency, $sale->rate));
            }
        }

        usort($rows, fn (array $a, array $b) => $b['revenue']->compare($a['revenue']));

        return array_slice($rows, 0, 20);
    }

    /**
     * Ombor qoldig'i: tannarx kirim kursi bilan, sotuv qiymati bugungi kurs bilan.
     *
     * @return array{units: int, accessories: int, cost: Money, value: Money, aging: array{fresh: int, d30: int, d60: int, d90: int}}
     */
    public function stock(?int $branchId = null): array
    {
        $units = StockUnit::query()
            ->where('status', StockStatus::InStock)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get();

        $cost = Money::uzs(0);
        $value = Money::uzs(0);
        $aging = ['fresh' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0];
        $today = CarbonImmutable::today();

        foreach ($units as $unit) {
            $cost = $cost->plus($this->uzs($unit->cost, $unit->cost_currency, $unit->cost_rate));
            $value = $value->plus($this->uzs($unit->sale_price, $unit->sale_currency, null));

            $days = $unit->created_at ? (int) $unit->created_at->diffInDays($today) : 0;
            $aging[match (true) {
                $days >= 90 => 'd90',
                $days >= 60 => 'd60',
                $days >= 30 => 'd30',
                default => 'fresh',
            }]++;
        }

        $batches = StockBatch::query()
            ->with('variant')
            ->where('qty_left', '>', 0)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get();

        foreach ($batches as $batch) {
            $cost = $cost->plus($this->uzs($batch->unit_cost, $batch->cost_currency, $batch->cost_rate)->times($batch->qty_left));
            $value = $value->plus($this->uzs($batch->variant->sale_price, $batch->variant->sale_currency, null)->times($batch->qty_left));
        }

        return [
            'units' => $units->count(),
            'accessories' => (int) $batches->sum('qty_left'),
            'cost' => $cost,
            'value' => $value,
            'aging' => $aging,
        ];
    }

    /**
     * Har kassa va valyuta bo'yicha: davr boshidagi qoldiq, kirim, chiqim, oxirgi qoldiq.
     *
     * @return list<array{account_id: int, branch_id: int|null, type: string, type_label: string, currency: Currency, opening: Money, in: Money, out: Money, closing: Money}>
     */
    public function cash(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null, bool $adminOnly = false): array
    {
        $accounts = CashAccount::query()
            ->when($adminOnly, fn ($query) => $query->whereNull('branch_id'))
            ->when(! $adminOnly && $branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($accounts as $account) {
            foreach (Currency::cases() as $currency) {
                $sum = fn (CashDirection $direction, ?CarbonImmutable $start, ?CarbonImmutable $end) => Money::of(
                    (string) CashMovement::query()
                        ->where('cash_account_id', $account->id)
                        ->where('currency', $currency)
                        ->where('direction', $direction)
                        ->when($start, fn ($query) => $query->where('created_at', '>=', $start))
                        ->when($end, fn ($query) => $query->where('created_at', '<=', $end))
                        ->sum('amount'),
                    $currency,
                );

                $beforeEnd = $from->startOfDay()->subSecond();
                $opening = $sum(CashDirection::In, null, $beforeEnd)->minus($sum(CashDirection::Out, null, $beforeEnd));
                $in = $sum(CashDirection::In, $from->startOfDay(), $to->endOfDay());
                $out = $sum(CashDirection::Out, $from->startOfDay(), $to->endOfDay());

                $rows[] = [
                    'account_id' => $account->id,
                    'branch_id' => $account->branch_id,
                    'type' => $account->type->value,
                    'type_label' => $account->type->label(),
                    'currency' => $currency,
                    'opening' => $opening,
                    'in' => $in,
                    'out' => $out,
                    'closing' => $opening->plus($in)->minus($out),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array{name: string, type_label: string, purchases: int, total: Money}>
     */
    public function purchases(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array
    {
        $rows = [];

        $purchases = Purchase::query()
            ->with(['supplier:id,name'])
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get();

        foreach ($purchases as $purchase) {
            $name = $purchase->supplier->name ?? $purchase->type->label();
            $rows[$name] ??= ['name' => $name, 'type_label' => $purchase->type->label(), 'purchases' => 0, 'total' => Money::uzs(0)];
            $rows[$name]['purchases']++;
            $rows[$name]['total'] = $rows[$name]['total']->plus($this->uzs($purchase->total, $purchase->currency, $purchase->rate));
        }

        usort($rows, fn (array $a, array $b) => $b['total']->compare($a['total']));

        return $rows;
    }

    /**
     * Dollardagi summani so'mga o'tkazadi: hujjat kursi bilan, u bo'lmasa bugungi kurs bilan.
     */
    public function uzs(string $amount, Currency $currency, ?string $rate): Money
    {
        if ($currency === Currency::UZS) {
            return Money::uzs($amount);
        }

        $rate ??= $this->todayRate();

        return $rate !== null ? Money::usd($amount)->convertTo(Currency::UZS, $rate) : Money::uzs(0);
    }

    private ?string $todayRate = null;

    private bool $todayRateLoaded = false;

    private function todayRate(): ?string
    {
        if (! $this->todayRateLoaded) {
            $this->todayRate = $this->rates->current()?->usd_uzs;
            $this->todayRateLoaded = true;
        }

        return $this->todayRate;
    }

    /**
     * @return Builder<Sale>
     */
    private function sales(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId)
    {
        return Sale::query()
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
    }

    private const MONTHS = ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyun', 'Iyul', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek'];
}
