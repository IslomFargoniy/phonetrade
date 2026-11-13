<?php

namespace App\Models;

use App\Enums\CashAccountType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CashAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Kassa. Har filialda uch turda bittadan; branch_id null bo'lsa — egasining admin kassasi.
 * Balans alohida saqlanmaydi: so'm va dollar qoldig'i harakatlardan hisoblanadi.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $branch_id
 * @property CashAccountType $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['branch_id', 'type'])]
class CashAccount extends Model
{
    /** @use HasFactory<CashAccountFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CashAccountType::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->branch_id === null;
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
