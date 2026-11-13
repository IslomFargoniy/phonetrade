<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InstallmentRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Nasiya ustamasi: filialda oylar soniga qarab foiz.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int $months
 * @property string $markup_pct
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['branch_id', 'months', 'markup_pct'])]
class InstallmentRate extends Model
{
    /** @use HasFactory<InstallmentRateFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Sotuvchi faqat shu muddatlardan birini tanlaydi. Qiymat — yangi filial uchun boshlang'ich foiz.
     *
     * @var array<int, string>
     */
    public const DEFAULTS = [3 => '20', 6 => '35', 9 => '45', 12 => '55'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'months' => 'integer',
            'markup_pct' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
