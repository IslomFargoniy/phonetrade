<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Savdo. Jami summa savdo valyutasida; har qator o'z tannarx nusxasi va foydasini saqlaydi.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int|null $user_id
 * @property int|null $customer_id
 * @property int $number
 * @property SaleType $type
 * @property Currency $currency
 * @property string|null $rate
 * @property string $subtotal
 * @property string $discount
 * @property string $total
 * @property string $change
 * @property SaleStatus $status
 * @property Carbon|null $created_at
 */
#[Fillable(['branch_id', 'user_id', 'customer_id', 'number', 'type', 'currency', 'rate', 'subtotal', 'discount', 'total', 'change', 'status'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'type' => SaleType::class,
            'currency' => Currency::class,
            'rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'change' => 'decimal:2',
            'status' => SaleStatus::class,
        ];
    }

    /**
     * @return HasOne<InstallmentPlan, $this>
     */
    public function plan(): HasOne
    {
        return $this->hasOne(InstallmentPlan::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * @return HasMany<SaleReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
