<?php

namespace App\Console\Commands;

use App\Enums\Currency;
use App\Enums\PlanStatus;
use App\Enums\SaleStatus;
use App\Enums\StockStatus;
use App\Models\CashAccount;
use App\Models\InstallmentPlan;
use App\Models\SaleItem;
use App\Models\StockUnit;
use App\Models\Tenant;
use App\Services\Cash\CashService;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Kunlik solishtirish. Hisob bir joyda saqlanib, boshqa joyda hisoblanadigan
 * raqamlar bir-biriga mos kelishini tekshiradi; nomuvofiqlik topilsa jurnalga yozadi.
 */
#[Signature('ledger:check')]
#[Description("Kassa, nasiya va ombor yozuvlari o'zaro mos kelishini tekshiradi")]
class CheckLedger extends Command
{
    public function handle(TenantContext $context, CashService $cash): int
    {
        $problems = [];

        Tenant::query()->each(function (Tenant $tenant) use ($context, $cash, &$problems): void {
            $context->runFor($tenant, function () use ($tenant, $cash, &$problems): void {
                foreach ($this->check($cash) as $problem) {
                    $problems[] = "[{$tenant->name}] {$problem}";
                }
            });
        });

        if ($problems === []) {
            $this->info('Nomuvofiqlik topilmadi.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error($problem);
            Log::error('ledger:check — '.$problem);
        }

        return self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function check(CashService $cash): array
    {
        $problems = [];

        // Kassa hech qachon manfiy bo'lmaydi: chiqim qoldiqdan oshsa rad etiladi.
        foreach (CashAccount::query()->get() as $account) {
            foreach (Currency::cases() as $currency) {
                if ($cash->balance($account, $currency)->isNegative()) {
                    $problems[] = "Kassa #{$account->id} ({$currency->value}) balansi manfiy.";
                }
            }
        }

        // Shartnomada to'langan summa to'lovlar yig'indisiga teng bo'lishi kerak.
        foreach (InstallmentPlan::query()->withSum('payments', 'amount')->get() as $plan) {
            $paid = Money::of((string) ($plan->getAttribute('payments_sum_amount') ?? '0'), $plan->currency);

            if (! $paid->equals(Money::of($plan->paid_total, $plan->currency))) {
                $problems[] = "Shartnoma #{$plan->id}: to'langan {$plan->paid_total}, to'lovlar yig'indisi {$paid->amount}.";
            }

            if ($plan->status === PlanStatus::Closed && ! $plan->remaining()->isZero()) {
                $problems[] = "Shartnoma #{$plan->id} yopilgan, lekin qarz qolgan.";
            }
        }

        // Sotilgan telefon aynan bitta qaytarilmagan savdoda turishi kerak.
        foreach (StockUnit::query()->where('status', StockStatus::Sold)->get() as $unit) {
            $open = SaleItem::query()
                ->where('stock_unit_id', $unit->id)
                ->whereColumn('returned_qty', '<', 'qty')
                ->whereHas('sale', fn ($sale) => $sale->where('status', '!=', SaleStatus::Returned))
                ->count();

            if ($open !== 1) {
                $problems[] = "Telefon {$unit->imei1}: holati \"sotilgan\", lekin ochiq savdolar soni {$open}.";
            }
        }

        return $problems;
    }
}
