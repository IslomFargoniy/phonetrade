<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:webhook')]
#[Description('Telegram botni shu saytga ulaydi (token o\'rnatilgandan keyin bir marta ishga tushiriladi)')]
class SetTelegramWebhook extends Command
{
    public function handle(TelegramClient $telegram): int
    {
        $secret = (string) config('services.telegram.webhook_secret');

        if (! $telegram->enabled() || $secret === '') {
            $this->error('.env faylida TELEGRAM_BOT_TOKEN va TELEGRAM_WEBHOOK_SECRET bo\'lishi kerak.');

            return self::FAILURE;
        }

        $url = route('telegram.webhook');

        if (! $telegram->setWebhook($url, $secret)) {
            $this->error('Telegram webhookni qabul qilmadi. Token to\'g\'riligini tekshiring.');

            return self::FAILURE;
        }

        $telegram->setCommands([
            'katalog' => "Do'kondagi tovarlar va narxlar",
            'qarz' => "Qolgan qarz va navbatdagi to'lov",
            'hisobot' => "Bugungi savdo va foyda (do'kon egasi)",
            'qarzdorlar' => "Muddati o'tgan to'lovlar (do'kon egasi)",
            'stop' => 'Botdan uzish',
        ]);

        $this->info("Webhook o'rnatildi: {$url}");

        return self::SUCCESS;
    }
}
