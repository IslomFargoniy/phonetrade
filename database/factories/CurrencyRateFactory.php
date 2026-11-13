<?php

namespace Database\Factories;

use App\Models\CurrencyRate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CurrencyRate>
 */
class CurrencyRateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'date' => now()->toDateString(),
            'usd_uzs' => '12750.00',
            'source' => CurrencyRate::SOURCE_MANUAL,
            'user_id' => null,
        ];
    }
}
