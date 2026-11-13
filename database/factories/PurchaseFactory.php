<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\PurchaseType;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $a) => Branch::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'type' => PurchaseType::Opening,
            'date' => now()->toDateString(),
            'currency' => Currency::UZS,
            'total' => '0.00',
        ];
    }
}
