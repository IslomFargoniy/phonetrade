<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $a) => Branch::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'expense_category_id' => fn (array $a) => ExpenseCategory::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'cash_account_id' => fn (array $a) => CashAccount::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'amount' => '100000.00',
            'currency' => Currency::UZS,
            'date' => now()->toDateString(),
        ];
    }
}
