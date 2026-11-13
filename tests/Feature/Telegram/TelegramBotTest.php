<?php

namespace Tests\Feature\Telegram;

use App\Actions\Sales\CloseSale;
use App\Enums\Currency;
use App\Jobs\SendTelegramMessage;
use App\Models\Customer;
use App\Models\InstallmentPlan;
use App\Models\InstallmentScheduleItem;
use App\Services\Installments\InstallmentTerms;
use App\Services\Telegram\TelegramBot;
use App\Services\Telegram\TelegramClient;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\StoreFixture;
use Tests\TestCase;

class TelegramBotTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER_CHAT = 111;

    private const CUSTOMER_CHAT = 222;

    private StoreFixture $store;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.token' => 'test-token',
            'services.telegram.username' => 'phonetrade_test_bot',
            'services.telegram.webhook_secret' => 'hook-secret',
        ]);

        $this->travelTo('2026-11-05 14:00:00');
        $this->store = StoreFixture::create();
        $this->customer = Customer::query()->create(['full_name' => 'Aliyev Jasur', 'phone' => '998901234567']);

        // Bot so'rovlari hech qaysi do'kon nomidan kelmaydi.
        app(TenantContext::class)->forget();
        Bus::fake([SendTelegramMessage::class]);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function update(int $chatId, array $message, string $secret = 'hook-secret'): TestResponse
    {
        return $this->postJson('/api/telegram/webhook', [
            'update_id' => random_int(1, 999999),
            'message' => ['chat' => ['id' => $chatId, 'type' => 'private'], 'from' => ['id' => $chatId], ...$message],
        ], ['X-Telegram-Bot-Api-Secret-Token' => $secret]);
    }

    private function linkOwner(): void
    {
        $this->store->owner->forceFill(['telegram_chat_id' => self::OWNER_CHAT])->save();
    }

    private function linkCustomer(): void
    {
        $this->customer->forceFill(['telegram_chat_id' => self::CUSTOMER_CHAT])->save();
    }

    /**
     * 500 000 so'mlik telefon, 3 oy, 20% ustama: oyiga 200 000, birinchisi savdo paytida.
     */
    private function openPlan(): InstallmentPlan
    {
        return app(TenantContext::class)->runFor($this->store->tenant, function (): InstallmentPlan {
            $unit = $this->store->receivePhone(1, ['unit_cost' => '450000', 'sale_price' => '500000', 'max_discount_pct' => '0'], 'UZS');

            return app(CloseSale::class)->handle(
                $this->store->branch,
                $this->store->owner,
                [['stock_unit_id' => $unit->id]],
                [['method' => 'cash', 'currency' => 'UZS', 'amount' => '200000']],
                $this->customer->id,
                new InstallmentTerms(Currency::UZS, 3, 13, '0'),
            )->plan()->firstOrFail();
        });
    }

    private function nextUnpaid(InstallmentPlan $plan): InstallmentScheduleItem
    {
        return $plan->schedule()->whereColumn('paid_amount', '<', 'amount')->orderBy('no')->firstOrFail();
    }

    private function assertReplied(int $chatId, string $fragment): void
    {
        Bus::assertDispatched(
            SendTelegramMessage::class,
            fn (SendTelegramMessage $job) => $job->chatId === $chatId && str_contains($job->text, $fragment),
        );
    }

    public function test_webhook_rejects_requests_without_the_secret(): void
    {
        $this->update(self::OWNER_CHAT, ['text' => '/start'], secret: 'wrong')->assertForbidden();

        config(['services.telegram.webhook_secret' => null]);
        $this->update(self::OWNER_CHAT, ['text' => '/start'], secret: '')->assertForbidden();

        Bus::assertNothingDispatched();
    }

    public function test_owner_links_with_a_one_time_code(): void
    {
        $link = app(TelegramBot::class)->linkFor($this->store->owner, app(TelegramClient::class));
        $this->assertNotNull($link);
        $this->assertStringStartsWith('https://t.me/phonetrade_test_bot?start=', $link);
        $token = Str::after($link, 'start=');

        $this->update(self::OWNER_CHAT, ['text' => "/start {$token}"])->assertOk();

        $this->assertSame(self::OWNER_CHAT, $this->store->owner->refresh()->telegram_chat_id);
        $this->assertReplied(self::OWNER_CHAT, 'Ulandi');

        // Kod bir martalik: boshqa odam shu havola bilan ulana olmaydi.
        $this->update(999, ['text' => "/start {$token}"])->assertOk();
        $this->assertSame(self::OWNER_CHAT, $this->store->owner->refresh()->telegram_chat_id);
        $this->assertReplied(999, 'Havola eskirgan');
    }

    public function test_owner_gets_todays_numbers_and_the_overdue_list(): void
    {
        $this->linkOwner();
        $plan = $this->openPlan();

        $this->update(self::OWNER_CHAT, ['text' => '/hisobot'])->assertOk();
        $this->assertReplied(self::OWNER_CHAT, 'Savdolar: <b>1 ta</b>');
        $this->assertReplied(self::OWNER_CHAT, "Muddati o'tgan: yo'q");

        $this->travelTo($this->nextUnpaid($plan)->due_date->addDays(4)->setTime(12, 0));

        $this->update(self::OWNER_CHAT, ['text' => '/qarzdorlar'])->assertOk();
        $this->assertReplied(self::OWNER_CHAT, "Aliyev Jasur, +998 90 123 45 67 — 200 000 so'm, 4 kun kechikkan");
    }

    public function test_reports_are_not_given_to_anyone_but_a_linked_owner(): void
    {
        $this->linkCustomer();
        $this->openPlan();

        $this->update(self::CUSTOMER_CHAT, ['text' => '/hisobot'])->assertOk();
        $this->update(self::CUSTOMER_CHAT, ['text' => '/qarzdorlar'])->assertOk();

        Bus::assertNotDispatched(SendTelegramMessage::class, fn (SendTelegramMessage $job) => str_contains($job->text, 'Savdolar'));
        Bus::assertNotDispatched(SendTelegramMessage::class, fn (SendTelegramMessage $job) => str_contains($job->text, 'Aliyev'));
        $this->assertReplied(self::CUSTOMER_CHAT, "faqat do'kon egasi uchun");
    }

    public function test_group_chats_are_ignored(): void
    {
        $this->linkOwner();

        $this->postJson('/api/telegram/webhook', [
            'message' => ['chat' => ['id' => self::OWNER_CHAT, 'type' => 'group'], 'from' => ['id' => self::OWNER_CHAT], 'text' => '/hisobot'],
        ], ['X-Telegram-Bot-Api-Secret-Token' => 'hook-secret'])->assertOk();

        Bus::assertNothingDispatched();
    }

    public function test_customer_links_by_sharing_their_own_contact(): void
    {
        // Birovning kontakti: bog'lanmaydi.
        $this->update(self::CUSTOMER_CHAT, ['contact' => ['phone_number' => '+998901234567', 'user_id' => 555]])->assertOk();
        $this->assertNull($this->customer->refresh()->telegram_chat_id);

        $this->update(self::CUSTOMER_CHAT, ['contact' => ['phone_number' => '+998901234567', 'user_id' => self::CUSTOMER_CHAT]])->assertOk();
        $this->assertSame(self::CUSTOMER_CHAT, $this->customer->refresh()->telegram_chat_id);
        $this->assertReplied(self::CUSTOMER_CHAT, 'Ulandingiz');

        // Do'konda yo'q raqam.
        $this->update(333, ['contact' => ['phone_number' => '+998937770011', 'user_id' => 333]])->assertOk();
        $this->assertReplied(333, 'mijoz topilmadi');
    }

    public function test_customer_sees_only_their_own_debt(): void
    {
        $this->linkCustomer();
        $this->openPlan();

        $this->update(self::CUSTOMER_CHAT, ['text' => '/qarz'])->assertOk();
        $this->assertReplied(self::CUSTOMER_CHAT, "Qolgan qarz: <b>400 000 so'm</b>");

        $this->update(444, ['text' => '/qarz'])->assertOk();
        Bus::assertNotDispatched(SendTelegramMessage::class, fn (SendTelegramMessage $job) => $job->chatId === 444 && str_contains($job->text, '400 000'));
    }

    public function test_reminder_goes_once_three_days_before_the_due_date(): void
    {
        $this->linkCustomer();
        $due = $this->nextUnpaid($this->openPlan())->due_date;

        $this->travelTo($due->subDays(4)->setTime(10, 0));
        $this->artisan('telegram:remind')->assertSuccessful();
        Bus::assertNothingDispatched();

        $this->travelTo($due->subDays(3)->setTime(10, 0));
        $this->artisan('telegram:remind')->assertSuccessful();
        $this->artisan('telegram:remind')->assertSuccessful();

        Bus::assertDispatchedTimes(SendTelegramMessage::class, 1);
        $this->assertReplied(self::CUSTOMER_CHAT, "To'lov kuniga 3 kun qoldi.");
        $this->assertReplied(self::CUSTOMER_CHAT, "To'lov: <b>200 000 so'm</b>, ".$due->format('d.m.Y'));

        $this->travelTo($due->setTime(10, 0));
        $this->artisan('telegram:remind')->assertSuccessful();
        $this->assertReplied(self::CUSTOMER_CHAT, "Bugun to'lov kuni.");
        Bus::assertDispatchedTimes(SendTelegramMessage::class, 2);
    }

    public function test_no_reminder_for_unlinked_customers_or_paid_instalments(): void
    {
        $plan = $this->openPlan();
        $row = $this->nextUnpaid($plan);

        $this->travelTo($row->due_date->subDays(3)->setTime(10, 0));
        $this->artisan('telegram:remind')->assertSuccessful();
        Bus::assertNothingDispatched();

        $this->linkCustomer();
        $row->forceFill(['paid_amount' => $row->amount])->save();
        $this->artisan('telegram:remind')->assertSuccessful();
        Bus::assertNothingDispatched();
    }

    public function test_daily_report_goes_to_linked_owners_only(): void
    {
        $this->artisan('telegram:daily-report')->assertSuccessful();
        Bus::assertNothingDispatched();

        $this->linkOwner();
        $this->artisan('telegram:daily-report')->assertSuccessful();

        Bus::assertDispatchedTimes(SendTelegramMessage::class, 1);
        // Xabar HTML rejimida ketadi: do'kon nomidagi maxsus belgilar ekranlanadi.
        $this->assertReplied(self::OWNER_CHAT, e($this->store->tenant->name));
    }

    public function test_nothing_is_sent_while_the_bot_is_not_configured(): void
    {
        config(['services.telegram.token' => null]);
        $this->linkOwner();
        $this->linkCustomer();

        $this->artisan('telegram:daily-report')->assertSuccessful();
        $this->artisan('telegram:remind')->assertSuccessful();

        Bus::assertNothingDispatched();
        $this->assertNull(app(TelegramBot::class)->linkFor($this->store->owner, app(TelegramClient::class)));
    }

    public function test_blocked_bot_unlinks_the_chat(): void
    {
        $this->linkOwner();
        $this->linkCustomer();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Forbidden: bot was blocked by the user'], 403)]);

        (new SendTelegramMessage(self::OWNER_CHAT, 'salom'))->handle(app(TelegramClient::class));

        $this->assertNull($this->store->owner->refresh()->telegram_chat_id);
        $this->assertSame(self::CUSTOMER_CHAT, $this->customer->refresh()->telegram_chat_id);
    }

    public function test_stop_unlinks(): void
    {
        $this->linkOwner();

        $this->update(self::OWNER_CHAT, ['text' => '/stop'])->assertOk();

        $this->assertNull($this->store->owner->refresh()->telegram_chat_id);
    }

    public function test_owner_sees_the_link_and_can_unlink_in_settings(): void
    {
        $this->actingAs($this->store->owner)
            ->get('/settings/store')
            ->assertInertia(fn ($page) => $page
                ->where('telegram.enabled', true)
                ->where('telegram.linked', false)
                ->where('telegram.link', fn ($link) => str_starts_with((string) $link, 'https://t.me/phonetrade_test_bot?start=')));

        $this->linkOwner();
        app(TenantContext::class)->forget();

        $this->actingAs($this->store->owner)->delete('/settings/store/telegram')->assertRedirect();
        $this->assertNull($this->store->owner->refresh()->telegram_chat_id);
    }
}
