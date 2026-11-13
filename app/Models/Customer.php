<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $full_name
 * @property string $phone
 * @property string|null $phone2
 * @property string|null $passport_series
 * @property string|null $passport_number
 * @property string|null $pinfl
 * @property string|null $address
 */
#[Fillable(['full_name', 'phone', 'phone2', 'passport_series', 'passport_number', 'pinfl', 'address'])]
class Customer extends Model
{
    /** @use HasFactory<CustomerFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return HasMany<CustomerFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(CustomerFile::class);
    }

    /**
     * @return HasMany<InstallmentPlan, $this>
     */
    public function plans(): HasMany
    {
        return $this->hasMany(InstallmentPlan::class);
    }

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
