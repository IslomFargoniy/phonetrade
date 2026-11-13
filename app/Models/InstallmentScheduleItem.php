<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * To'lov jadvalining bir qatori.
 *
 * @property int $id
 * @property int $installment_plan_id
 * @property int $no
 * @property Carbon $due_date
 * @property string $amount
 * @property string $paid_amount
 */
#[Fillable(['installment_plan_id', 'no', 'due_date', 'amount', 'paid_amount'])]
class InstallmentScheduleItem extends Model
{
    protected $table = 'installment_schedule';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'no' => 'integer',
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
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
