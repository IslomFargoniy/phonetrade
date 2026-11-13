<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CashSweepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kassa bo'shatish hujjati.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
#[Fillable(['branch_id', 'user_id'])]
class CashSweep extends Model
{
    /** @use HasFactory<CashSweepFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
