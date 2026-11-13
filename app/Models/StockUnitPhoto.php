<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StockUnitPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Bitta telefonning rasmi.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $stock_unit_id
 * @property string $path
 * @property int $sort
 */
#[Fillable(['stock_unit_id', 'path', 'sort'])]
class StockUnitPhoto extends Model
{
    /** @use HasFactory<StockUnitPhotoFactory> */
    use BelongsToTenant, HasFactory;

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * @return BelongsTo<StockUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(StockUnit::class, 'stock_unit_id');
    }
}
