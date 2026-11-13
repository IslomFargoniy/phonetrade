<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Jobs\SendTelegramMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use App\Services\Telegram\TelegramReports;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:daily-report')]
#[Description('Botga ulangan do\'kon egalariga bugungi savdo va foyda hisobotini yuboradi')]
class SendTelegramDailyReport extends Command
{
    public function handle(TelegramClient $telegram, TelegramReports $reports): int
    {
        if (! $telegram->enabled()) {
            $this->warn('Telegram bot sozlanmagan (TELEGRAM_BOT_TOKEN).');

            return self::SUCCESS;
        }

        $sent = 0;

        User::query()
            ->where('role', Role::Owner)
            ->where('is_active', true)
            ->whereNotNull('telegram_chat_id')
            ->each(function (User $owner) use ($reports, &$sent): void {
                $tenant = Tenant::query()->find($owner->tenant_id);

                if ($tenant === null || $tenant->isBlocked()) {
                    return;
                }

                SendTelegramMessage::dispatch((int) $owner->telegram_chat_id, $reports->daily($tenant));
                $sent++;
            });

        $this->info("Hisobot navbatga qo'yildi: {$sent} ta.");

        return self::SUCCESS;
    }
}
