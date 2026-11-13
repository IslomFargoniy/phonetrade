<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\User;
use App\Services\Telegram\TelegramClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Telegram xabarini navbat orqali yuboradi. Foydalanuvchi botni bloklagan bo'lsa,
 * bog'lanish uziladi — keyingi xabarlar behuda yuborilmaydi.
 */
class SendTelegramMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function __construct(
        public int $chatId,
        public string $text,
        public ?array $replyMarkup = null,
    ) {}

    public function handle(TelegramClient $telegram): void
    {
        $sent = $telegram->send($this->chatId, $this->text, $this->replyMarkup);

        if ($sent === false) {
            User::query()->where('telegram_chat_id', $this->chatId)->update(['telegram_chat_id' => null]);
            Customer::query()->where('telegram_chat_id', $this->chatId)->update(['telegram_chat_id' => null]);

            return;
        }

        if ($sent === null && $telegram->enabled()) {
            throw new RuntimeException('Telegram xabari yuborilmadi, qayta uriniladi.');
        }
    }
}
