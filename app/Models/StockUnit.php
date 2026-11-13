<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\StockStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StockUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Bitta telefon: o'z IMEI'si, barcode'i, tannarxi va sotuv narxi bilan.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int $product_id
 * @property int|null $purchase_item_id
 * @property string $imei1
 * @property string|null $imei2
 * @property string $barcode
 * @property array<string, string>|null $attributes
 * @property string $cost
 * @property Currency $cost_currency
 * @property string|null $cost_rate
 * @property string $sale_price
 * @property Currency $sale_currency
 * @property string $max_discount_pct
 * @property int $warranty_months
 * @property StockStatus $status
 * @property Carbon|null $created_at
 */
#[Fillable(['branch_id', 'product_id', 'purchase_item_id', 'imei1', 'imei2', 'barcode', 'attributes', 'cost', 'cost_currency', 'cost_rate', 'sale_price', 'sale_currency', 'max_discount_pct', 'warranty_months', 'status'])]
class StockUnit extends Model
{
    /** @use HasFactory<StockUnitFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'cost' => 'decimal:2',
            'cost_currency' => Currency::class,
            'cost_rate' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'sale_currency' => Currency::class,
            'max_discount_pct' => 'decimal:2',
            'warranty_months' => 'integer',
            'status' => StockStatus::class,
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<PurchaseItem, $this>
     */
    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    /**
     * @return HasMany<StockUnitPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(StockUnitPhoto::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Asosiy rasm: telefonning o'z birinchi rasmi, bo'lmasa mahsulot (model) rasmi.
     */
    public function imageUrl(): ?string
    {
        return $this->photos->first()?->url() ?? $this->product->imageUrl();
    }
}
