<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Mahsulot: brend va model (masalan, Samsung Galaxy A55).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $category_id
 * @property string $name
 * @property string|null $image_path
 * @property int $default_warranty_months
 * @property string $default_max_discount_pct
 * @property bool $is_active
 */
#[Fillable(['category_id', 'name', 'default_warranty_months', 'default_max_discount_pct', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_warranty_months' => 'integer',
            'default_max_discount_pct' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Rasmning to'liq manzili (mobil ilova ham shu manzildan yuklaydi). Rasm bo'lmasa null.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path !== null ? Storage::disk('public')->url($this->image_path) : null;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
