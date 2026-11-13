<?php

namespace App\Models;

use App\Enums\Currency;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Miqdor bilan yuritiladigan tovar varianti (masalan, qora silikon chexol): bitta barcode, narx shu yerda.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $product_id
 * @property string $barcode
 * @property array<string, string>|null $attributes
 * @property string $sale_price
 * @property Currency $sale_currency
 * @property string $max_discount_pct
 * @property int $warranty_months
 */
#[Fillable(['product_id', 'barcode', 'attributes', 'sale_price', 'sale_currency', 'max_discount_pct', 'warranty_months'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'sale_price' => 'decimal:2',
            'sale_currency' => Currency::class,
            'max_discount_pct' => 'decimal:2',
            'warranty_months' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<StockBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(StockBatch::class, 'variant_id');
    }
}
