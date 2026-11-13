<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\InstallmentRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallmentRate>
 */
class InstallmentRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'tenant_id' => fn (array $attributes) => Branch::withoutGlobalScopes()->whereKey($attributes['branch_id'])->value('tenant_id'),
            'months' => 3,
            'markup_pct' => '20.00',
        ];
    }
}
