<?php

namespace App\Models;

use App\Enums\Currency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $purchase_id
 * @property int $product_id
 * @property int|null $variant_id
 * @property int $qty
 * @property string $unit_cost
 * @property string $sale_price
 * @property Currency $sale_currency
 * @property string $max_discount_pct
 * @property int $warranty_months
 * @property array<string, string>|null $attributes
 */
#[Fillable(['purchase_id', 'product_id', 'variant_id', 'qty', 'unit_cost', 'sale_price', 'sale_currency', 'max_discount_pct', 'warranty_months', 'attributes'])]
class PurchaseItem extends Model
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
            'sale_price' => 'decimal:2',
            'sale_currency' => Currency::class,
            'max_discount_pct' => 'decimal:2',
            'warranty_months' => 'integer',
            'attributes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<StockUnit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(StockUnit::class);
    }
}
