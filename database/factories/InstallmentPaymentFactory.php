<?php

namespace Database\Factories;

use App\Models\InstallmentPayment;
use App\Models\InstallmentPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallmentPayment>
 */
class InstallmentPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'installment_plan_id' => fn (array $a) => InstallmentPlan::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'amount' => '200000.00',
            'profit_amount' => '50000.00',
            'paid_at' => now(),
        ];
    }
}
