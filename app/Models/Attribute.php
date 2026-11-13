<?php

namespace App\Models;

use App\Enums\AttributeType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kriteriya (rang, xotira, holat ...). Do'kon egasi o'zi sozlaydi.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property AttributeType $type
 * @property int $sort
 */
#[Fillable(['name', 'type', 'sort'])]
class Attribute extends Model
{
    /** @use HasFactory<AttributeFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<AttributeOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(AttributeOption::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }
}
