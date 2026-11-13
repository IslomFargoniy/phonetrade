<?php

namespace App\Actions\Branches;

use App\Enums\CashAccountType;
use App\Models\Branch;
use App\Models\InstallmentRate;
use Illuminate\Support\Facades\DB;

/**
 * Filial ochadi. Har filial uchta kassa (naqd, karta, o'tkazma) va
 * 3/6/9/12 oylik ustama qatorlari bilan birga yaratiladi.
 */
class CreateBranch
{
    /**
     * @param  array{name: string, address?: string|null, phone?: string|null}  $data
     */
    public function handle(array $data): Branch
    {
        return DB::transaction(function () use ($data): Branch {
            $branch = Branch::query()->create([
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);

            foreach (CashAccountType::cases() as $type) {
                $branch->cashAccounts()->create(['type' => $type]);
            }

            foreach (InstallmentRate::DEFAULTS as $months => $markup) {
                $branch->installmentRates()->create([
                    'months' => $months,
                    'markup_pct' => $markup,
                ]);
            }

            return $branch;
        });
    }
}
