<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Do'kon obuna uchun to'lagan pul. Superadmin qo'lda kiritadi; do'kon o'zi ko'rmaydi
 * va o'zgartira olmaydi, shuning uchun do'kon konteksti qo'llanmaydi.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $plan_id
 * @property string $plan_name
 * @property string $amount
 * @property int $months
 * @property Carbon $period_from
 * @property Carbon $period_to
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 */
#[Fillable(['tenant_id', 'plan_id', 'plan_name', 'amount', 'months', 'period_from', 'period_to', 'note', 'created_by'])]
class SubscriptionPayment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'months' => 'integer',
            'period_from' => 'date',
            'period_to' => 'date',
        ];
    }
}
