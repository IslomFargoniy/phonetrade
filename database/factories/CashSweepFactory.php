<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CashSweep;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashSweep>
 */
class CashSweepFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $a) => Branch::factory()->create(['tenant_id' => $a['tenant_id']])->id,
        ];
    }
}
