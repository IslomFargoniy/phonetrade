<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InstallmentPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Nasiya bo'yicha tushgan to'lov. Foyda ulushi to'lov paytida yoziladi.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $installment_plan_id
 * @property int|null $user_id
 * @property string $amount
 * @property string $profit_amount
 * @property bool $is_initial
 * @property Carbon $paid_at
 */
#[Fillable(['installment_plan_id', 'user_id', 'amount', 'profit_amount', 'is_initial', 'paid_at'])]
class InstallmentPayment extends Model
{
    /** @use HasFactory<InstallmentPaymentFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'profit_amount' => 'decimal:2',
            'is_initial' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<InstallmentPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(InstallmentPlan::class, 'installment_plan_id');
    }
}
