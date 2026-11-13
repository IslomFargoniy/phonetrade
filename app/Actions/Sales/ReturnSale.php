<?php

namespace App\Actions\Sales;

use App\Enums\CashAccountType;
use App\Enums\SaleStatus;
use App\Enums\StockStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\CashAccount;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockBatch;
use App\Models\StockUnit;
use App\Models\User;
use App\Services\Cash\CashService;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Savdoni to'liq yoki qisman qaytaradi: pul kassadan chiqadi, tovar omborga
 * qaytadi, shu qismning foydasi teskari yoziladi. Muddat cheklovi yo'q.
 */
class ReturnSale
{
    public function __construct(private readonly CashService $cash) {}

    /**
     * @param  list<array{sale_item_id: int, qty: int}>  $items
     */
    public function handle(Sale $sale, User $user, array $items, CashAccountType $refundFrom, ?string $reason = null): SaleReturn
    {
        return DB::transaction(function () use ($sale, $user, $items, $refundFrom, $reason): SaleReturn {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $saleItems = SaleItem::query()->where('sale_id', $sale->id)->with('batches')->lockForUpdate()->get()->keyBy('id');

            $refund = Money::zero($sale->currency);
            $profit = Money::zero($sale->currency);
            $lines = [];

            foreach ($items as $line) {
                $item = $saleItems->get($line['sale_item_id']);
                $qty = $line['qty'];

                if ($item === null || $qty < 1 || $qty > $item->qty - $item->returned_qty) {
                    throw new BusinessRuleException('RETURN_INVALID', "Qaytariladigan miqdor noto'g'ri.", 'items');
                }

                $previous = SaleReturnItem::query()->where('sale_item_id', $item->id);
                $amount = $this->share($item->final_price, (string) $previous->clone()->sum('amount'), $item, $qty, $sale);
                $itemProfit = $this->share($item->profit, (string) $previous->clone()->sum('profit'), $item, $qty, $sale);

                $this->restock($item, $qty);
                $item->update(['returned_qty' => $item->returned_qty + $qty]);

                $refund = $refund->plus($amount);
                $profit = $profit->plus($itemProfit);
                $lines[] = ['sale_item_id' => $item->id, 'qty' => $qty, 'amount' => $amount->amount, 'profit' => $itemProfit->amount];
            }

            if ($lines === []) {
                throw new BusinessRuleException('RETURN_INVALID', 'Qaytariladigan tovarni tanlang.', 'items');
            }

            $return = SaleReturn::query()->create([
                'sale_id' => $sale->id,
                'user_id' => $user->id,
                'reason' => $reason,
                'refund_amount' => $refund->amount,
                'profit_reversed' => $profit->amount,
            ]);
            $return->items()->createMany($lines);

            if ($refund->isPositive()) {
                $account = CashAccount::query()->where('branch_id', $sale->branch_id)->where('type', $refundFrom)->firstOrFail();
                $this->cash->withdraw($account, $refund, 'sale_return', $return, $sale->rate);
            }

            $fullyReturned = SaleItem::query()->where('sale_id', $sale->id)->whereColumn('returned_qty', '<', 'qty')->doesntExist();
            $sale->update(['status' => $fullyReturned ? SaleStatus::Returned : SaleStatus::PartiallyReturned]);

            Audit::log('sale.returned', $sale, null, ['refund' => $refund->amount, 'reason' => $reason]);

            return $return;
        });
    }

    /**
     * Qatorning qaytarilayotgan qismiga to'g'ri keladigan summa.
     *
     * Ulush bir marta, to'liq aniqlikda hisoblanib keyin yaxlitlanadi. Qatorning oxirgi
     * qismi qaytganda esa avval qaytarilganidan qolgan hamma summa olinadi — shunda
     * bo'lib-bo'lib qaytarilganda ham yig'indi qator summasiga tiyingacha teng chiqadi.
     */
    private function share(string $lineTotal, string $alreadyReturned, SaleItem $item, int $qty, Sale $sale): Money
    {
        $total = Money::of($lineTotal, $sale->currency);

        if ($item->returned_qty + $qty === $item->qty) {
            return $total->minus(Money::of($alreadyReturned, $sale->currency));
        }

        return Money::of(
            bcdiv(bcmul($total->amount, (string) $qty, 10), (string) $item->qty, 10),
            $sale->currency,
        );
    }

    private function restock(SaleItem $item, int $qty): void
    {
        if ($item->stock_unit_id !== null) {
            // Telefon "qaytgan" holatida turadi: tekshiruvdan keyin egasi yana sotuvga chiqaradi.
            StockUnit::query()->whereKey($item->stock_unit_id)->update(['status' => StockStatus::Returned]);

            return;
        }

        // Aksesuar olingan partiyasiga qaytadi, oxirgi olingan partiyadan boshlab.
        $remaining = $qty;

        foreach ($item->batches->sortByDesc('id') as $taken) {
            if ($remaining === 0) {
                break;
            }

            $back = min($taken->qty - $taken->returned_qty, $remaining);

            if ($back < 1) {
                continue;
            }

            StockBatch::query()->whereKey($taken->stock_batch_id)->increment('qty_left', $back);
            $taken->update(['returned_qty' => $taken->returned_qty + $back]);
            $remaining -= $back;
        }
    }
}
