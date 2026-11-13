<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PurchaseType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tovar kirimi hujjati.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int|null $user_id
 * @property PurchaseType $type
 * @property int|null $supplier_id
 * @property int|null $customer_id
 * @property int|null $cash_account_id
 * @property Carbon $date
 * @property Currency $currency
 * @property string|null $rate
 * @property string $total
 * @property string|null $note
 */
#[Fillable(['branch_id', 'user_id', 'type', 'supplier_id', 'customer_id', 'cash_account_id', 'date', 'currency', 'rate', 'total', 'note'])]
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PurchaseType::class,
            'date' => 'date',
            'currency' => Currency::class,
            'rate' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
