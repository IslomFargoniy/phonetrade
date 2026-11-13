<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Services\Rates\CbuRateFetcher;
use App\Services\Rates\CurrencyRateService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rates:fetch')]
#[Description('Markaziy bank dollar kursini olib, bugungi kursi yo\'q do\'konlarga yozadi')]
class FetchCurrencyRates extends Command
{
    public function handle(CbuRateFetcher $fetcher, CurrencyRateService $rates, TenantContext $context): int
    {
        $rate = $fetcher->fetch();

        if ($rate === null) {
            $this->error('Kursni olib bo\'lmadi.');

            return self::FAILURE;
        }

        $stored = 0;

        Tenant::query()
            ->where('status', TenantStatus::Active)
            ->each(function (Tenant $tenant) use ($context, $rates, $rate, &$stored): void {
                if ($context->runFor($tenant, fn () => $rates->storeFromCbu($rate)) !== null) {
                    $stored++;
                }
            });

        $this->info("Kurs: {$rate}. Yangilangan do'konlar: {$stored}.");

        return self::SUCCESS;
    }
}
