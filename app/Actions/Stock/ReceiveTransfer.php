<?php

namespace App\Actions\Stock;

use App\Enums\StockStatus;
use App\Enums\TransferStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\StockBatch;
use App\Models\StockTransfer;
use App\Models\StockUnit;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Qabul qiluvchi filial ko'chirishni tasdiqlaydi: tovar uning omboriga tushadi.
 */
class ReceiveTransfer
{
    public function handle(StockTransfer $transfer, User $user): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user): StockTransfer {
            $transfer = StockTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if ($transfer->status !== TransferStatus::InTransit) {
                throw new BusinessRuleException('TRANSFER_ALREADY_RECEIVED', "Bu ko'chirish allaqachon qabul qilingan.", 'transfer');
            }

            foreach ($transfer->items as $item) {
                if ($item->stock_unit_id !== null) {
                    StockUnit::query()->whereKey($item->stock_unit_id)->update([
                        'branch_id' => $transfer->to_branch_id,
                        'status' => StockStatus::InStock,
                    ]);

                    continue;
                }

                StockBatch::query()->create([
                    'branch_id' => $transfer->to_branch_id,
                    'variant_id' => $item->variant_id,
                    'qty_in' => $item->qty,
                    'qty_left' => $item->qty,
                    'unit_cost' => $item->unit_cost,
                    'cost_currency' => $item->cost_currency,
                    'cost_rate' => $item->cost_rate,
                ]);
            }

            $transfer->update([
                'status' => TransferStatus::Received,
                'received_by' => $user->id,
                'received_at' => now(),
            ]);

            Audit::log('transfer.received', $transfer);

            return $transfer;
        });
    }
}
