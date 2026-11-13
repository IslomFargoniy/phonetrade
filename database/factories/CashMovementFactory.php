<?php

namespace Database\Factories;

use App\Enums\CashDirection;
use App\Enums\Currency;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'cash_account_id' => fn (array $a) => CashAccount::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'direction' => CashDirection::In,
            'amount' => '100000.00',
            'currency' => Currency::UZS,
            'kind' => 'test',
        ];
    }
}
