<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sale_id
 * @property int|null $stock_unit_id
 * @property int|null $variant_id
 * @property string $name
 * @property int $qty
 * @property int $returned_qty
 * @property string $price
 * @property Currency $currency
 * @property string $discount
 * @property string $final_price
 * @property string $cost_snapshot
 * @property string $profit
 * @property Carbon|null $warranty_until
 */
#[Fillable(['sale_id', 'stock_unit_id', 'variant_id', 'name', 'qty', 'returned_qty', 'price', 'currency', 'discount', 'final_price', 'cost_snapshot', 'profit', 'warranty_until'])]
class SaleItem extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'returned_qty' => 'integer',
            'price' => 'decimal:2',
            'currency' => Currency::class,
            'discount' => 'decimal:2',
            'final_price' => 'decimal:2',
            'cost_snapshot' => 'decimal:2',
            'profit' => 'decimal:2',
            'warranty_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
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

    /**
     * @return HasMany<SaleItemBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(SaleItemBatch::class);
    }
}
