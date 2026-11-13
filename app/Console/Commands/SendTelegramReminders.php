<?php

namespace App\Console\Commands;

use App\Enums\PlanStatus;
use App\Jobs\SendTelegramMessage;
use App\Models\InstallmentScheduleItem;
use App\Models\Tenant;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramReports;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Botga ulangan mijozlarga to'lov eslatmasi: 3 kun oldin, to'lov kuni va 3 kun kechikkanda.
 * Har bir eslatma bir marta yuboriladi (buyruq kuniga bir necha marta ishlasa ham).
 */
#[Signature('telegram:remind')]
#[Description("Mijozlarga nasiya to'lovi eslatmasini yuboradi")]
class SendTelegramReminders extends Command
{
    /** To'lov kunigacha qolgan kunlar; manfiy — kechikkan. */
    public const OFFSETS = [3, 0, -3];

    public function handle(TelegramClient $telegram, TelegramReports $reports): int
    {
        if (! $telegram->enabled()) {
            $this->warn('Telegram bot sozlanmagan (TELEGRAM_BOT_TOKEN).');

            return self::SUCCESS;
        }

        $today = CarbonImmutable::today();
        $stores = Tenant::query()->pluck('name', 'id');
        $sent = 0;

        foreach (self::OFFSETS as $daysLeft) {
            $rows = InstallmentScheduleItem::query()
                ->with(['plan.customer', 'plan.schedule'])
                ->whereColumn('paid_amount', '<', 'amount')
                ->whereDate('due_date', $today->addDays($daysLeft)->toDateString())
                ->whereHas('plan', fn ($plan) => $plan
                    ->where('status', PlanStatus::Active)
                    ->whereHas('customer', fn ($customer) => $customer->whereNotNull('telegram_chat_id')))
                ->get();

            foreach ($rows as $row) {
                $plan = $row->plan;

                // Kalit allaqachon bor bo'lsa — bu eslatma yuborilgan.
                if (DB::table('telegram_notices')->insertOrIgnore(['key' => "due:{$row->id}:{$daysLeft}", 'created_at' => now()]) === 0) {
                    continue;
                }

                SendTelegramMessage::dispatch(
                    (int) $plan->customer->telegram_chat_id,
                    $reports->reminder($row, $plan, (string) ($stores[$plan->tenant_id] ?? ''), $daysLeft),
                );
                $sent++;
            }
        }

        $this->info("Eslatma navbatga qo'yildi: {$sent} ta.");

        return self::SUCCESS;
    }
}
