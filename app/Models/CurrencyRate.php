<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CurrencyRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Do'konning kunlik dollar kursi (1 dollar necha so'm).
 *
 * @property int $id
 * @property int $tenant_id
 * @property Carbon $date
 * @property string $usd_uzs
 * @property string $source
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['date', 'usd_uzs', 'source', 'user_id'])]
class CurrencyRate extends Model
{
    /** @use HasFactory<CurrencyRateFactory> */
    use BelongsToTenant, HasFactory;

    public const SOURCE_CBU = 'cbu';

    public const SOURCE_MANUAL = 'manual';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'usd_uzs' => 'decimal:2',
        ];
    }
}
