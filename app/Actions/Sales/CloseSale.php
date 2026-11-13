<?php

namespace App\Actions\Sales;

use App\Enums\CashAccountType;
use App\Enums\Currency;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Enums\StockStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockUnit;
use App\Models\User;
use App\Services\Cash\CashService;
use App\Services\Rates\CurrencyRateService;
use App\Services\Sales\CartCalculator;
use App\Services\Sales\CartLine;
use App\Services\Sales\CartTotals;
use App\Services\Sales\SaleNumber;
use App\Services\Stock\BatchConsumer;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Savdoni yopadi. Hammasi bitta tranzaksiyada: dona band qilinadi, to'lovlar
 * kassaga tushadi, foyda yoziladi. Biror qadam o'tmasa, hech narsa saqlanmaydi.
 *
 * @phpstan-type Item array{stock_unit_id?: int|null, variant_id?: int|null, qty?: int|null, discount?: string|null}
 * @phpstan-type Payment array{method: string, currency: string, amount: string}
 */
class CloseSale
{
    public function __construct(
        private readonly CartCalculator $calculator,
        private readonly CashService $cash,
        private readonly CurrencyRateService $rates,
        private readonly BatchConsumer $batches,
        private readonly SaleNumber $numbers,
    ) {}

    /**
     * @param  list<Item>  $items
     * @param  list<Payment>  $payments
     */
    public function handle(Branch $branch, User $user, array $items, array $payments, ?int $customerId = null): Sale
    {
        $rate = $this->rates->current()?->usd_uzs;

        return DB::transaction(function () use ($branch, $user, $items, $payments, $customerId, $rate): Sale {
            $totals = $this->calculator->calculate($this->loadItems($branch, $items), $rate);
            $paid = $this->convertPayments($payments, $totals->currency, $rate);

            if ($paid['total']->lessThan($totals->total)) {
                throw new BusinessRuleException(
                    'PAYMENT_NOT_BALANCED',
                    sprintf(
                        "To'lov yetarli emas: jami %s, to'landi %s.",
                        $totals->total->format(),
                        $paid['total']->format(),
                    ),
                    'payments',
                );
            }

            $change = $paid['total']->minus($totals->total);

            $sale = Sale::query()->create([
                'branch_id' => $branch->id,
                'user_id' => $user->id,
                'customer_id' => $customerId,
                'number' => $this->numbers->next(),
                'type' => SaleType::Full,
                'currency' => $totals->currency,
                'rate' => $rate,
                'subtotal' => $totals->subtotal->amount,
                'discount' => $totals->discount->amount,
                'total' => $totals->total->amount,
                'change' => $change->amount,
                'status' => SaleStatus::Completed,
            ]);

            foreach ($totals->lines as $line) {
                $this->storeLine($sale, $branch, $line, $totals, $rate);
            }

            foreach ($paid['lines'] as $payment) {
                $account = $this->account($branch, $payment['method']);

                $this->cash->deposit($account, $payment['amount'], 'sale', $sale, $rate);

                $sale->payments()->create([
                    'cash_account_id' => $account->id,
                    'method' => $payment['method'],
                    'currency' => $payment['amount']->currency,
                    'amount' => $payment['amount']->amount,
                    'rate' => $rate,
                    'amount_base' => $payment['base']->amount,
                ]);
            }

            // Qaytim savdo valyutasida, naqd kassadan beriladi.
            if ($change->isPositive()) {
                $this->cash->withdraw($this->account($branch, CashAccountType::Cash), $change, 'change', $sale, $rate);
            }

            Audit::log('sale.closed', $sale, null, ['number' => $sale->number, 'total' => $sale->total]);

            return $sale;
        });
    }

    /**
     * Savatdagi tovarlarni yuklaydi va telefonlarni qulflaydi: ikki sotuvchi bitta
     * telefonni bir vaqtda sota olmaydi.
     *
     * @param  list<Item>  $items
     * @return list<array{unit: StockUnit|null, variant: ProductVariant|null, qty: int, discount: string}>
     */
    private function loadItems(Branch $branch, array $items): array
    {
        $unitIds = [];

        foreach ($items as $item) {
            if (isset($item['stock_unit_id'])) {
                $unitIds[] = $item['stock_unit_id'];
            }
        }

        if (count($unitIds) !== count(array_unique($unitIds))) {
            throw new BusinessRuleException('CART_INVALID', 'Bitta telefon savatga ikki marta qo\'shilgan.', 'items');
        }

        sort($unitIds);

        $units = StockUnit::query()->with('product')->whereIn('id', $unitIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $loaded = [];

        foreach ($items as $item) {
            $discount = (string) ($item['discount'] ?? '0');

            if (isset($item['stock_unit_id'])) {
                $unit = $units->get($item['stock_unit_id']);

                if ($unit === null || $unit->branch_id !== $branch->id || $unit->status !== StockStatus::InStock) {
                    throw new BusinessRuleException(
                        'STOCK_ALREADY_SOLD',
                        'Bu telefon omborda yo\'q: allaqachon sotilgan yoki boshqa filialda.',
                        'items',
                    );
                }

                $loaded[] = ['unit' => $unit, 'variant' => null, 'qty' => 1, 'discount' => $discount];

                continue;
            }

            $variant = ProductVariant::query()->with('product')->find($item['variant_id'] ?? null);
            $qty = (int) ($item['qty'] ?? 0);

            if ($variant === null || $qty < 1) {
                throw new BusinessRuleException('CART_INVALID', 'Savatdagi tovar topilmadi.', 'items');
            }

            $loaded[] = ['unit' => null, 'variant' => $variant, 'qty' => $qty, 'discount' => $discount];
        }

        return $loaded;
    }

    private function storeLine(Sale $sale, Branch $branch, CartLine $line, CartTotals $totals, ?string $rate): void
    {
        $cost = Money::zero($totals->currency);
        $consumed = [];
        $warrantyMonths = 0;

        if ($line->unit !== null) {
            $cost = $this->costIn($line->unit->cost, $line->unit->cost_currency, $line->unit->cost_rate, $totals->currency, $rate);
            $warrantyMonths = $line->unit->warranty_months;
            $line->unit->update(['status' => StockStatus::Sold]);
        } elseif ($line->variant !== null) {
            $warrantyMonths = $line->variant->warranty_months;
            $consumed = $this->batches->consume($line->variant, $branch, $line->qty);

            foreach ($consumed as $batch) {
                $cost = $cost->plus(
                    $this->costIn($batch['unit_cost'], $batch['cost_currency'], $batch['cost_rate'], $totals->currency, $rate)
                        ->times($batch['qty']),
                );
            }
        }

        $item = SaleItem::query()->create([
            'sale_id' => $sale->id,
            'stock_unit_id' => $line->unit?->id,
            'variant_id' => $line->variant?->id,
            'name' => $line->name,
            'qty' => $line->qty,
            'price' => $line->price->amount,
            'currency' => $line->price->currency,
            'discount' => $line->discount->amount,
            'final_price' => $line->final->amount,
            'cost_snapshot' => $cost->amount,
            'profit' => $line->final->minus($cost)->amount,
            'warranty_until' => $warrantyMonths > 0 ? now()->addMonthsNoOverflow($warrantyMonths)->toDateString() : null,
        ]);

        foreach ($consumed as $batch) {
            $item->batches()->create(['stock_batch_id' => $batch['batch_id'], 'qty' => $batch['qty']]);
        }
    }

    /**
     * Tannarxni savdo valyutasiga o'tkazadi. Kurs — kirim kunidagi (tovar bilan saqlangan);
     * u bo'lmasa (so'mda olingan tovar dollarda sotilganda) bugungi kurs.
     */
    private function costIn(string $amount, Currency $from, ?string $costRate, Currency $target, ?string $todayRate): Money
    {
        return $this->calculator->convert(Money::of($amount, $from), $target, $costRate ?? $todayRate);
    }

    /**
     * @param  list<Payment>  $payments
     * @return array{total: Money, lines: list<array{method: CashAccountType, amount: Money, base: Money}>}
     */
    private function convertPayments(array $payments, Currency $currency, ?string $rate): array
    {
        $total = Money::zero($currency);
        $lines = [];

        foreach ($payments as $payment) {
            $amount = Money::of($payment['amount'], Currency::from($payment['currency']));

            if (! $amount->isPositive()) {
                continue;
            }

            $base = $this->calculator->convert($amount, $currency, $rate);
            $total = $total->plus($base);
            $lines[] = ['method' => CashAccountType::from($payment['method']), 'amount' => $amount, 'base' => $base];
        }

        return ['total' => $total, 'lines' => $lines];
    }

    private function account(Branch $branch, CashAccountType $type): CashAccount
    {
        return CashAccount::query()->where('branch_id', $branch->id)->where('type', $type)->firstOrFail();
    }
}
