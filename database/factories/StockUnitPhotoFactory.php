<?php

namespace Database\Factories;

use App\Models\StockUnit;
use App\Models\StockUnitPhoto;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockUnitPhoto>
 */
class StockUnitPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'stock_unit_id' => fn (array $a) => StockUnit::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'path' => 'units/test.jpg',
            'sort' => 1,
        ];
    }
}
