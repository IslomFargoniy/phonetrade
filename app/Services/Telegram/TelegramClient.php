<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Telegram Bot API bilan ishlaydigan yupqa qatlam.
 */
class TelegramClient
{
    public function enabled(): bool
    {
        return filled(config('services.telegram.token'));
    }

    public function username(): ?string
    {
        $username = config('services.telegram.username');

        return is_string($username) && $username !== '' ? ltrim($username, '@') : null;
    }

    /**
     * Xabar yuboradi.
     *
     * @param  array<string, mixed>|null  $replyMarkup
     * @return bool|null true — yuborildi; false — foydalanuvchi botni bloklagan yoki chat yo'q
     *                   (bog'lanishni uzish kerak); null — vaqtinchalik xato, qayta urinish mumkin
     */
    public function send(int $chatId, string $text, ?array $replyMarkup = null): ?bool
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::timeout(10)->asJson()->post($this->url('sendMessage'), array_filter([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
                'reply_markup' => $replyMarkup,
            ], fn ($value) => $value !== null));
        } catch (ConnectionException) {
            return null;
        }

        if ($response->successful()) {
            return true;
        }

        // 403 — bot bloklangan; 400 "chat not found" — chat o'chirilgan.
        return in_array($response->status(), [400, 403], true) ? false : null;
    }

    public function setWebhook(string $url, string $secret): bool
    {
        return Http::timeout(15)->asJson()->post($this->url('setWebhook'), [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message'],
        ])->successful();
    }

    /**
     * Bot menyusida ko'rinadigan buyruqlar ro'yxati.
     *
     * @param  array<string, string>  $commands  buyruq => tavsif
     */
    public function setCommands(array $commands): bool
    {
        return Http::timeout(15)->asJson()->post($this->url('setMyCommands'), [
            'commands' => array_map(
                fn (string $command, string $description) => ['command' => $command, 'description' => $description],
                array_keys($commands),
                array_values($commands),
            ),
        ])->successful();
    }

    private function url(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram.token').'/'.$method;
    }
}
