<?php

namespace App\Actions\Stock;

use App\Enums\StockStatus;
use App\Enums\TransferStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\StockTransfer;
use App\Models\StockUnit;
use App\Models\User;
use App\Services\Stock\BatchConsumer;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Tovarni boshqa filialga jo'natadi. Jo'natilgan tovar "yo'lda" turadi va
 * qabul qiluvchi filial tasdiqlamaguncha hech qayerda sotilmaydi.
 */
class SendTransfer
{
    public function __construct(private readonly BatchConsumer $batches) {}

    /**
     * @param  list<int>  $unitIds
     * @param  list<array{variant_id: int, qty: int}>  $quantities
     */
    public function handle(Branch $from, Branch $to, User $user, array $unitIds, array $quantities = [], ?string $note = null): StockTransfer
    {
        if ($from->is($to)) {
            throw new BusinessRuleException('TRANSFER_SAME_BRANCH', "Tovarni o'sha filialning o'ziga ko'chirib bo'lmaydi.", 'to_branch_id');
        }

        if ($unitIds === [] && $quantities === []) {
            throw new BusinessRuleException('TRANSFER_EMPTY', "Ko'chiriladigan tovarni tanlang.", 'items');
        }

        return DB::transaction(function () use ($from, $to, $user, $unitIds, $quantities, $note): StockTransfer {
            $transfer = StockTransfer::query()->create([
                'from_branch_id' => $from->id,
                'to_branch_id' => $to->id,
                'status' => TransferStatus::InTransit,
                'user_id' => $user->id,
                'note' => $note,
            ]);

            $units = StockUnit::query()->whereIn('id', $unitIds)->lockForUpdate()->get();

            foreach ($unitIds as $unitId) {
                $unit = $units->firstWhere('id', $unitId);

                if ($unit === null || $unit->branch_id !== $from->id || $unit->status !== StockStatus::InStock) {
                    throw new BusinessRuleException('STOCK_UNAVAILABLE', "Tanlangan telefon shu filial omborida yo'q.", 'items');
                }

                $unit->update(['status' => StockStatus::InTransit]);
                $transfer->items()->create(['stock_unit_id' => $unit->id, 'qty' => 1]);
            }

            foreach ($quantities as $line) {
                $variant = ProductVariant::query()->findOrFail($line['variant_id']);

                // Tannarx partiyasi bilan birga ketadi, shuning uchun har partiya alohida qator.
                foreach ($this->batches->consume($variant, $from, $line['qty']) as $taken) {
                    $transfer->items()->create([
                        'variant_id' => $variant->id,
                        'qty' => $taken['qty'],
                        'unit_cost' => $taken['unit_cost'],
                        'cost_currency' => $taken['cost_currency'],
                        'cost_rate' => $taken['cost_rate'],
                    ]);
                }
            }

            Audit::log('transfer.sent', $transfer, null, ['from' => $from->id, 'to' => $to->id]);

            return $transfer;
        });
    }
}
