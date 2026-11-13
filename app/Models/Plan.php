<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Obuna tarifi. Platformaga tegishli — do'kon konteksti qo'llanmaydi.
 *
 * @property int $id
 * @property string $name
 * @property string $price
 * @property int|null $max_branches
 * @property int|null $max_users
 * @property bool $is_active
 * @property int $sort
 */
#[Fillable(['name', 'price', 'max_branches', 'max_users', 'is_active', 'sort'])]
class Plan extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'max_branches' => 'integer',
            'max_users' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
