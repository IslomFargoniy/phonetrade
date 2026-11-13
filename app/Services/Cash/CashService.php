<?php

namespace App\Services\Cash;

use App\Enums\CashDirection;
use App\Enums\Currency;
use App\Exceptions\BusinessRuleException;
use App\Models\CashAccount;
use App\Models\CashMovement;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Kassa bilan barcha amallar shu yerdan o'tadi.
 *
 * Balans alohida saqlanmaydi — harakatlar yig'indisidan hisoblanadi. Chiqimda
 * kassa qatori qulflanadi, shuning uchun ikki parallel chiqim qoldiqdan oshib ketmaydi.
 */
class CashService
{
    public function balance(CashAccount $account, Currency $currency): Money
    {
        $totals = CashMovement::query()
            ->where('cash_account_id', $account->id)
            ->where('currency', $currency)
            ->selectRaw("coalesce(sum(case when direction = 'in' then amount else 0 end), 0) as total_in")
            ->selectRaw("coalesce(sum(case when direction = 'out' then amount else 0 end), 0) as total_out")
            ->toBase()
            ->first();

        return Money::of((string) ($totals->total_in ?? '0'), $currency)
            ->minus(Money::of((string) ($totals->total_out ?? '0'), $currency));
    }

    /**
     * @return array{UZS: Money, USD: Money}
     */
    public function balances(CashAccount $account): array
    {
        return [
            'UZS' => $this->balance($account, Currency::UZS),
            'USD' => $this->balance($account, Currency::USD),
        ];
    }

    public function deposit(
        CashAccount $account,
        Money $amount,
        string $kind,
        ?Model $source = null,
        ?string $rate = null,
        ?string $note = null,
    ): CashMovement {
        $this->assertPositive($amount);

        return $this->record($account, CashDirection::In, $amount, $kind, $source, $rate, $note);
    }

    /**
     * Kassadan chiqim. Qoldiq yetmasa bajarilmaydi.
     */
    public function withdraw(
        CashAccount $account,
        Money $amount,
        string $kind,
        ?Model $source = null,
        ?string $rate = null,
        ?string $note = null,
    ): CashMovement {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($account, $amount, $kind, $source, $rate, $note): CashMovement {
            // Shu kassadan boshqa chiqim tugaguncha kutadi.
            CashAccount::query()->whereKey($account->id)->lockForUpdate()->first();

            $balance = $this->balance($account, $amount->currency);

            if ($balance->lessThan($amount)) {
                throw new BusinessRuleException(
                    'CASH_INSUFFICIENT',
                    sprintf(
                        'Kassada mablag\' yetarli emas: kerak %s, qoldiq %s.',
                        $amount->format(),
                        $balance->format(),
                    ),
                    'cash_account_id',
                );
            }

            return $this->record($account, CashDirection::Out, $amount, $kind, $source, $rate, $note);
        });
    }

    private function record(
        CashAccount $account,
        CashDirection $direction,
        Money $amount,
        string $kind,
        ?Model $source,
        ?string $rate,
        ?string $note,
    ): CashMovement {
        return CashMovement::query()->create([
            'cash_account_id' => $account->id,
            'direction' => $direction,
            'amount' => $amount->amount,
            'currency' => $amount->currency,
            'rate' => $rate,
            'kind' => $kind,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'user_id' => Auth::id(),
            'note' => $note,
        ]);
    }

    private function assertPositive(Money $amount): void
    {
        if (! $amount->isPositive()) {
            throw new BusinessRuleException('AMOUNT_INVALID', "Summa noldan katta bo'lishi kerak.", 'amount');
        }
    }
}
