<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBot $bot): JsonResponse
    {
        $secret = (string) config('services.telegram.webhook_secret');

        // Faqat Telegram biladigan sir: begona so'rov bot nomidan ish qila olmaydi.
        abort_unless(
            $secret !== '' && hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')),
            403,
        );

        try {
            $bot->handle($request->all());
        } catch (Throwable $exception) {
            // Xato javob Telegram'ni o'sha xabarni qayta-qayta yuborishga majbur qiladi.
            Log::error('Telegram webhook: '.$exception->getMessage(), ['exception' => $exception]);
        }

        return response()->json(['ok' => true]);
    }
}
