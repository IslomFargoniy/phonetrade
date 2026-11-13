<?php

namespace App\Models;

use App\Enums\TransferStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StockTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Filiallar orasida tovar ko'chirish.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $from_branch_id
 * @property int $to_branch_id
 * @property TransferStatus $status
 * @property int|null $user_id
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property string|null $note
 * @property Carbon|null $created_at
 */
#[Fillable(['from_branch_id', 'to_branch_id', 'status', 'user_id', 'received_by', 'received_at', 'note'])]
class StockTransfer extends Model
{
    /** @use HasFactory<StockTransferFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<StockTransferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }
}
