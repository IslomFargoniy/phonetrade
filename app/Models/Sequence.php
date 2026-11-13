<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Do'kon ichidagi ketma-ket raqam hisoblagichi.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $key
 * @property int $value
 */
#[Fillable(['key', 'value'])]
class Sequence extends Model
{
    /** @use HasFactory<SequenceFactory> */
    use BelongsToTenant, HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }
}
