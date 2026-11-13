<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_transfer_id
 * @property int|null $stock_unit_id
 * @property int|null $variant_id
 * @property int $qty
 * @property string|null $unit_cost
 * @property Currency|null $cost_currency
 * @property string|null $cost_rate
 */
#[Fillable(['stock_transfer_id', 'stock_unit_id', 'variant_id', 'qty', 'unit_cost', 'cost_currency', 'cost_rate'])]
class StockTransferItem extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_cost' => 'decimal:2',
            'cost_currency' => Currency::class,
            'cost_rate' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<StockUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(StockUnit::class, 'stock_unit_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
