<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Mijoz hujjati (pasport rasmi). Fayl yopiq diskda saqlanadi.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_id
 * @property string $path
 * @property string|null $original_name
 */
#[Fillable(['customer_id', 'path', 'original_name'])]
class CustomerFile extends Model
{
    /** @use HasFactory<CustomerFileFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
