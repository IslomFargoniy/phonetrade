<?php

namespace App\Actions\Cash;

use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\User;
use App\Services\Cash\CashService;
use App\Services\Rates\CurrencyRateService;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Xarajatni yozadi va kassadan chiqim qiladi. Kassada pul yetmasa xarajat saqlanmaydi.
 */
class RecordExpense
{
    public function __construct(
        private readonly CashService $cash,
        private readonly CurrencyRateService $rates,
    ) {}

    public function handle(Branch $branch, User $user, int $categoryId, int $cashAccountId, Money $amount, ?string $note = null): Expense
    {
        $rate = $this->rates->current()?->usd_uzs;

        return DB::transaction(function () use ($branch, $user, $categoryId, $cashAccountId, $amount, $note, $rate): Expense {
            $account = CashAccount::query()->whereKey($cashAccountId)->where('branch_id', $branch->id)->first();

            if ($account === null) {
                throw new BusinessRuleException('CASH_ACCOUNT_REQUIRED', 'Shu filial kassasini tanlang.', 'cash_account_id');
            }

            $expense = Expense::query()->create([
                'branch_id' => $branch->id,
                'expense_category_id' => $categoryId,
                'cash_account_id' => $account->id,
                'user_id' => $user->id,
                'amount' => $amount->amount,
                'currency' => $amount->currency,
                'rate' => $rate,
                'date' => now()->toDateString(),
                'note' => $note,
            ]);

            $this->cash->withdraw($account, $amount, 'expense', $expense, $rate, $note);

            Audit::log('expense.recorded', $expense, null, ['amount' => $amount->amount, 'currency' => $amount->currency->value]);

            return $expense;
        });
    }
}
