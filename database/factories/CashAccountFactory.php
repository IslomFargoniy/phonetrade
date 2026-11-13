<?php

namespace Database\Factories;

use App\Enums\CashAccountType;
use App\Models\CashAccount;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashAccount>
 */
class CashAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => null,
            'type' => CashAccountType::Cash,
        ];
    }
}
