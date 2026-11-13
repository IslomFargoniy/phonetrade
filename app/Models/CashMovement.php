<?php

namespace App\Models;

use App\Enums\CashDirection;
use App\Enums\Currency;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CashMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kassa harakati. Yozuv o'zgartirilmaydi: xato teskari yozuv bilan tuzatiladi.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $cash_account_id
 * @property CashDirection $direction
 * @property string $amount
 * @property Currency $currency
 * @property string|null $rate
 * @property string $kind
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $user_id
 * @property string|null $note
 * @property Carbon|null $created_at
 */
#[Fillable(['cash_account_id', 'direction', 'amount', 'currency', 'rate', 'kind', 'source_type', 'source_id', 'user_id', 'note'])]
class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => CashDirection::class,
            'amount' => 'decimal:2',
            'currency' => Currency::class,
            'rate' => 'decimal:2',
        ];
    }

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }
}
