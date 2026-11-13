<?php

namespace App\Models;

use App\Enums\CashAccountType;
use App\Enums\Currency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $cash_account_id
 * @property CashAccountType $method
 * @property Currency $currency
 * @property string $amount
 * @property string|null $rate
 * @property string $amount_base
 */
#[Fillable(['sale_id', 'cash_account_id', 'method', 'currency', 'amount', 'rate', 'amount_base'])]
class SalePayment extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => CashAccountType::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount_base' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }
}
