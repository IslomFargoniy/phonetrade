<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SaleReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Savdoni qaytarish hujjati.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $sale_id
 * @property int|null $user_id
 * @property string|null $reason
 * @property string $refund_amount
 * @property string $profit_reversed
 * @property Carbon|null $created_at
 */
#[Fillable(['sale_id', 'user_id', 'reason', 'refund_amount', 'profit_reversed'])]
class SaleReturn extends Model
{
    /** @use HasFactory<SaleReturnFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'refund_amount' => 'decimal:2',
            'profit_reversed' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<SaleReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
