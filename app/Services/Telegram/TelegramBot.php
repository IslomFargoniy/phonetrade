<?php

namespace App\Services\Telegram;

use App\Enums\Role;
use App\Jobs\SendTelegramMessage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalog\PublicCatalog;
use App\Support\Phone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Botga kelgan xabarlarni qayta ishlaydi.
 *
 * - Do'kon egasi web paneldagi havola (`/start <kod>`) orqali ulanadi.
 * - Mijoz "Telefon raqamni yuborish" tugmasi bilan o'z kontaktini yuboradi va shu raqamdagi
 *   mijoz kartalariga bog'lanadi.
 */
class TelegramBot
{
    private const LINK_MINUTES = 30;

    public function __construct(
        private readonly TelegramReports $reports,
        private readonly PublicCatalog $catalog,
    ) {}

    /**
     * Egasi uchun bir martalik ulanish havolasi. Bot sozlanmagan bo'lsa null.
     */
    public function linkFor(User $user, TelegramClient $client): ?string
    {
        if (! $client->enabled() || $client->username() === null) {
            return null;
        }

        $token = Cache::remember("telegram-link-user:{$user->id}", now()->addMinutes(self::LINK_MINUTES), function () use ($user): string {
            $token = Str::random(32);
            Cache::put("telegram-link:{$token}", $user->id, now()->addMinutes(self::LINK_MINUTES));

            return $token;
        });

        return 'https://t.me/'.$client->username().'?start='.$token;
    }

    /**
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): void
    {
        $message = $update['message'] ?? null;

        // Faqat shaxsiy chat: guruhda hisobot yoki qarz ma'lumoti ko'rinmasligi kerak.
        if (! is_array($message) || ($message['chat']['type'] ?? null) !== 'private') {
            return;
        }

        $chatId = (int) ($message['chat']['id'] ?? 0);
        $fromId = (int) ($message['from']['id'] ?? 0);

        if ($chatId === 0) {
            return;
        }

        if (isset($message['contact']) && is_array($message['contact'])) {
            $this->linkCustomer($chatId, $fromId, $message['contact']);

            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        [$command, $argument] = array_pad(preg_split('/\s+/', $text, 2) ?: [], 2, '');
        $command = Str::lower(Str::before($command, '@'));

        match ($command) {
            '/start' => match (true) {
                str_starts_with($argument, 'shop_') => $this->openCatalog($chatId, Str::after($argument, 'shop_')),
                $argument !== '' => $this->linkOwner($chatId, $argument),
                default => $this->welcome($chatId),
            },
            '/katalog' => $this->catalogs($chatId),
            '/hisobot' => $this->forOwner($chatId, fn (Tenant $tenant) => $this->reports->daily($tenant)),
            '/qarzdorlar' => $this->forOwner($chatId, fn (Tenant $tenant) => $this->reports->overdue($tenant)),
            '/qarz' => $this->reply($chatId, $this->reports->customerDebts($chatId)),
            '/stop' => $this->unlink($chatId),
            default => $this->help($chatId),
        };
    }

    private function linkOwner(int $chatId, string $token): void
    {
        $userId = Cache::pull("telegram-link:{$token}");
        $user = is_int($userId)
            ? User::query()->whereKey($userId)->where('role', Role::Owner)->where('is_active', true)->first()
            : null;

        if ($user === null) {
            $this->reply($chatId, "Havola eskirgan yoki noto'g'ri. Web paneldagi Sozlamalar → Do'kon bo'limidan yangisini oling.");

            return;
        }

        Cache::forget("telegram-link-user:{$user->id}");
        $user->forceFill(['telegram_chat_id' => $chatId])->save();

        $this->reply($chatId, implode("\n", [
            'Ulandi: <b>'.e($user->name).'</b>.',
            'Har kuni kechqurun savdo va foyda hisoboti keladi.',
            '',
            '/hisobot — bugungi hisobot',
            "/qarzdorlar — muddati o'tgan to'lovlar",
            '/stop — botdan uzish',
        ]));
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function linkCustomer(int $chatId, int $fromId, array $contact): void
    {
        // Faqat o'z raqami: birovning kontaktini yuborib uning qarzini ko'rib bo'lmaydi.
        if ((int) ($contact['user_id'] ?? 0) !== $fromId) {
            $this->reply($chatId, "Faqat o'z telefon raqamingizni pastdagi tugma orqali yuboring.", $this->contactKeyboard());

            return;
        }

        $phone = Phone::normalize((string) ($contact['phone_number'] ?? ''));
        $linked = $phone !== null
            ? Customer::query()->where('phone', $phone)->update(['telegram_chat_id' => $chatId])
            : 0;

        if ($linked === 0) {
            $this->reply($chatId, "Bu raqam bo'yicha mijoz topilmadi. Do'konda qoldirgan raqamingiz boshqa bo'lishi mumkin.", ['remove_keyboard' => true]);

            return;
        }

        $this->reply($chatId, implode("\n", [
            "Ulandingiz. To'lov kunidan oldin eslatma yuboramiz.",
            '',
            "/qarz — qolgan qarz va navbatdagi to'lov",
            '/stop — eslatmalarni o\'chirish',
        ]), ['remove_keyboard' => true]);
    }

    /**
     * Do'kon tarqatgan havola orqali kelgan mijozga katalogni ochadigan tugma yuboriladi.
     */
    private function openCatalog(int $chatId, string $code): void
    {
        $tenant = $this->catalog->findByCode($code);

        if ($tenant === null) {
            $this->reply($chatId, "Bu do'kon katalogi hozir yopiq.");

            return;
        }

        $this->reply($chatId, '<b>'.e($tenant->name).'</b> — hozir do\'konda bor tovarlar va narxlar.', $this->catalogButtons([$tenant]));
    }

    /**
     * Mijoz ulangan do'konlarning kataloglari.
     */
    private function catalogs(int $chatId): void
    {
        $tenants = Tenant::query()
            ->whereIn('id', Customer::query()->where('telegram_chat_id', $chatId)->pluck('tenant_id'))
            ->get()
            ->filter(fn (Tenant $tenant) => $this->catalog->isEnabled($tenant) && ! $tenant->isBlocked())
            ->values()
            ->all();

        if ($tenants === []) {
            $this->reply($chatId, "Katalogni ochish uchun do'kon bergan havoladan foydalaning.");

            return;
        }

        $this->reply($chatId, "Do'konni tanlang:", $this->catalogButtons($tenants));
    }

    /**
     * @param  array<int, Tenant>  $tenants
     * @return array<string, mixed>
     */
    private function catalogButtons(array $tenants): array
    {
        return ['inline_keyboard' => array_map(fn (Tenant $tenant) => [[
            'text' => count($tenants) === 1 ? 'Katalogni ochish' : $tenant->name,
            'web_app' => ['url' => (string) $this->catalog->url($tenant)],
        ]], array_values($tenants))];
    }

    private function welcome(int $chatId): void
    {
        $this->reply(
            $chatId,
            "Assalomu alaykum! Muddatli to'lov eslatmalarini olish uchun pastdagi tugma orqali telefon raqamingizni yuboring.",
            $this->contactKeyboard(),
        );
    }

    private function help(int $chatId): void
    {
        $isOwner = User::query()->where('telegram_chat_id', $chatId)->exists();

        $this->reply($chatId, $isOwner
            ? "/hisobot — bugungi hisobot\n/qarzdorlar — muddati o'tgan to'lovlar\n/stop — botdan uzish"
            : "/katalog — do'kondagi tovarlar\n/qarz — qolgan qarz va navbatdagi to'lov\n/start — telefon raqam orqali ulanish\n/stop — eslatmalarni o'chirish");
    }

    private function unlink(int $chatId): void
    {
        User::query()->where('telegram_chat_id', $chatId)->update(['telegram_chat_id' => null]);
        Customer::query()->where('telegram_chat_id', $chatId)->update(['telegram_chat_id' => null]);

        $this->reply($chatId, 'Uzildi. Endi xabar yuborilmaydi. Qayta ulanish uchun /start.', ['remove_keyboard' => true]);
    }

    /**
     * @param  callable(Tenant): string  $build
     */
    private function forOwner(int $chatId, callable $build): void
    {
        $owner = User::query()
            ->where('telegram_chat_id', $chatId)
            ->where('role', Role::Owner)
            ->where('is_active', true)
            ->first();
        $tenant = $owner !== null ? Tenant::query()->find($owner->tenant_id) : null;

        if ($tenant === null || $tenant->isBlocked()) {
            $this->reply($chatId, "Bu buyruq faqat do'kon egasi uchun. Ulanish havolasi web paneldagi Sozlamalar → Do'kon bo'limida.");

            return;
        }

        $this->reply($chatId, $build($tenant));
    }

    /**
     * @param  array<string, mixed>|null  $markup
     */
    private function reply(int $chatId, string $text, ?array $markup = null): void
    {
        SendTelegramMessage::dispatch($chatId, $text, $markup);
    }

    /**
     * @return array<string, mixed>
     */
    private function contactKeyboard(): array
    {
        return [
            'keyboard' => [[['text' => 'Telefon raqamni yuborish', 'request_contact' => true]]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];
    }
}
