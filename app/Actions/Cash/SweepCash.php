<?php

namespace App\Actions\Cash;

use App\Enums\Currency;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\CashSweep;
use App\Models\User;
use App\Services\Cash\CashService;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Kassa bo'shatish: filialning har kassasidagi butun qoldiq (so'm va dollar alohida)
 * egasining shu turdagi admin kassasiga o'tadi. Filial balansi nolga tushadi.
 */
class SweepCash
{
    public function __construct(private readonly CashService $cash) {}

    public function handle(Branch $branch, User $user): CashSweep
    {
        return DB::transaction(function () use ($branch, $user): CashSweep {
            $accounts = CashAccount::query()->where('branch_id', $branch->id)->orderBy('id')->lockForUpdate()->get();
            $adminAccounts = CashAccount::query()->whereNull('branch_id')->get()->keyBy(fn (CashAccount $account) => $account->type->value);

            $sweep = CashSweep::query()->create(['branch_id' => $branch->id, 'user_id' => $user->id]);
            $moved = [];

            foreach ($accounts as $account) {
                $admin = $adminAccounts->get($account->type->value);

                if ($admin === null) {
                    throw new BusinessRuleException('ADMIN_ACCOUNT_MISSING', 'Admin kassa topilmadi.', 'branch');
                }

                foreach (Currency::cases() as $currency) {
                    $balance = $this->cash->balance($account, $currency);

                    if (! $balance->isPositive()) {
                        continue;
                    }

                    $this->cash->withdraw($account, $balance, 'sweep_out', $sweep);
                    $this->cash->deposit($admin, $balance, 'sweep_in', $sweep);

                    $moved[] = "{$account->type->value}:{$currency->value}:{$balance->amount}";
                }
            }

            if ($moved === []) {
                throw new BusinessRuleException('CASH_EMPTY', "Kassada o'tkaziladigan pul yo'q.", 'branch');
            }

            Audit::log('cash.swept', $sweep, null, ['branch_id' => $branch->id, 'moved' => $moved]);

            return $sweep;
        });
    }
}
