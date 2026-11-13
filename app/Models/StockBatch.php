<?php

namespace App\Models;

use App\Enums\Currency;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StockBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Aksesuar partiyasi. Sotuvda tannarx eng eski partiyadan boshlab olinadi (FIFO).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int $variant_id
 * @property int|null $purchase_item_id
 * @property int $qty_in
 * @property int $qty_left
 * @property string $unit_cost
 * @property Currency $cost_currency
 * @property string|null $cost_rate
 * @property Carbon|null $created_at
 */
#[Fillable(['branch_id', 'variant_id', 'purchase_item_id', 'qty_in', 'qty_left', 'unit_cost', 'cost_currency', 'cost_rate'])]
class StockBatch extends Model
{
    /** @use HasFactory<StockBatchFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_in' => 'integer',
            'qty_left' => 'integer',
            'unit_cost' => 'decimal:2',
            'cost_currency' => Currency::class,
            'cost_rate' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
