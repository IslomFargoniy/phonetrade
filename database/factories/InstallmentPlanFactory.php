<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\PlanStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\Sale;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallmentPlan>
 */
class InstallmentPlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sale_id' => fn (array $a) => Sale::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'customer_id' => fn (array $a) => Customer::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'branch_id' => fn (array $a) => Branch::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'currency' => Currency::UZS,
            'mode' => 'A',
            'price' => '500000.00',
            'markup_pct' => '20.00',
            'months' => 3,
            'pay_day' => 13,
            'total_debt' => '600000.00',
            'total_revenue' => '600000.00',
            'total_profit' => '150000.00',
            'profit_ratio' => '0.25',
            'status' => PlanStatus::Active,
        ];
    }
}
