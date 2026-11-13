<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PlanStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Database\Factories\InstallmentPlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Muddatli to'lov shartnomasi.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $sale_id
 * @property int $customer_id
 * @property int $branch_id
 * @property Currency $currency
 * @property string $mode
 * @property string $price
 * @property string $down_payment
 * @property string $markup_pct
 * @property int $months
 * @property int $pay_day
 * @property string $total_debt
 * @property string $total_revenue
 * @property string $total_profit
 * @property numeric-string $profit_ratio
 * @property string $paid_total
 * @property PlanStatus $status
 * @property Carbon|null $created_at
 */
#[Fillable(['sale_id', 'customer_id', 'branch_id', 'currency', 'mode', 'price', 'down_payment', 'markup_pct', 'months', 'pay_day', 'total_debt', 'total_revenue', 'total_profit', 'profit_ratio', 'paid_total', 'status'])]
class InstallmentPlan extends Model
{
    /** @use HasFactory<InstallmentPlanFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'price' => 'decimal:2',
            'down_payment' => 'decimal:2',
            'markup_pct' => 'decimal:2',
            'months' => 'integer',
            'pay_day' => 'integer',
            'total_debt' => 'decimal:2',
            'total_revenue' => 'decimal:2',
            'total_profit' => 'decimal:2',
            'profit_ratio' => 'decimal:10',
            'paid_total' => 'decimal:2',
            'status' => PlanStatus::class,
        ];
    }

    /**
     * Hali to'lanmagan summa (shartnoma valyutasida).
     */
    public function remaining(): Money
    {
        return Money::of($this->total_revenue, $this->currency)->minus(Money::of($this->paid_total, $this->currency));
    }

    /**
     * @return HasMany<InstallmentScheduleItem, $this>
     */
    public function schedule(): HasMany
    {
        return $this->hasMany(InstallmentScheduleItem::class);
    }

    /**
     * @return HasMany<InstallmentPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(InstallmentPayment::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
