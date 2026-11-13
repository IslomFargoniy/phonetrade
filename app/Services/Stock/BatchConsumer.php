<?php

namespace App\Services\Stock;

use App\Enums\Currency;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\StockBatch;

/**
 * Aksesuar qoldig'idan FIFO tartibida oladi: avval eng eski partiya tugaydi.
 */
class BatchConsumer
{
    public function available(ProductVariant $variant, Branch $branch): int
    {
        return (int) StockBatch::query()
            ->where('variant_id', $variant->id)
            ->where('branch_id', $branch->id)
            ->sum('qty_left');
    }

    /**
     * Qoldiqni kamaytiradi va qaysi partiyadan nechta olinganini qaytaradi.
     * DB tranzaksiyasi ichida chaqirilishi kerak.
     *
     * @return list<array{batch_id: int, qty: int, unit_cost: string, cost_currency: Currency, cost_rate: string|null}>
     */
    public function consume(ProductVariant $variant, Branch $branch, int $qty): array
    {
        $batches = StockBatch::query()
            ->where('variant_id', $variant->id)
            ->where('branch_id', $branch->id)
            ->where('qty_left', '>', 0)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($batches->sum('qty_left') < $qty) {
            throw new BusinessRuleException(
                'STOCK_INSUFFICIENT',
                sprintf('Omborda yetarli emas: kerak %d dona, bor %d dona.', $qty, $batches->sum('qty_left')),
                'qty',
            );
        }

        $taken = [];
        $remaining = $qty;

        foreach ($batches as $batch) {
            if ($remaining === 0) {
                break;
            }

            $take = min($batch->qty_left, $remaining);
            $batch->decrement('qty_left', $take);
            $remaining -= $take;

            $taken[] = [
                'batch_id' => $batch->id,
                'qty' => $take,
                'unit_cost' => $batch->unit_cost,
                'cost_currency' => $batch->cost_currency,
                'cost_rate' => $batch->cost_rate,
            ];
        }

        return $taken;
    }
}
