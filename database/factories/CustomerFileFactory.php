<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerFile;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerFile>
 */
class CustomerFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => fn (array $a) => Customer::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'path' => 'customers/test.jpg',
        ];
    }
}
