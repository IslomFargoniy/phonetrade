<?php

use App\Enums\TenantStatus;
use App\Jobs\SendTelegramMessage;
use App\Services\Catalog\PublicCatalog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Bus;
use Tests\Support\StoreFixture;

beforeEach(function () {
    $this->store = StoreFixture::create();
    $this->store->receivePhone(1);
    $this->store->receivePhone(2);
    $this->store->receiveCases(5);

    app(TenantContext::class)->forget();
});

function openStorefront(StoreFixture $store): string
{
    app(PublicCatalog::class)->setEnabled($store->tenant, true);

    return (string) $store->tenant->refresh()->setting('catalog.code');
}

it('is closed until the owner opens it', function () {
    $this->get('/vitrina/abcdefghijkl')->assertNotFound();

    $code = openStorefront($this->store);
    $this->get("/vitrina/{$code}")->assertOk();

    app(PublicCatalog::class)->setEnabled($this->store->tenant->refresh(), false);
    $this->get("/vitrina/{$code}")->assertNotFound();

    // Qayta ochilganda mijozlarga tarqatilgan havola o'zgarmaydi.
    expect(openStorefront($this->store))->toBe($code);
});

it('is not served for a blocked store', function () {
    $code = openStorefront($this->store);
    $this->store->tenant->update(['status' => TenantStatus::Blocked]);

    $this->get("/vitrina/{$code}")->assertNotFound();
});

it('shows what is in stock with prices and nothing confidential', function () {
    $code = openStorefront($this->store);

    $response = $this->get("/vitrina/{$code}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/show')
            ->where('store.name', $this->store->tenant->name)
            // Ikki bir xil telefon bitta qatorga yig'iladi, aksesuar alohida.
            ->has('items', 2)
            ->where('items.0.name', 'Chexol Galaxy A55')
            ->where('items.0.price_label', "60 000 so'm")
            ->where('items.1.name', 'Samsung Galaxy A55')
            ->where('items.1.price_label', "4 850 000 so'm")
            ->where('items.1.few', false)
            ->missing('items.1.qty')
            ->missing('items.1.cost')
            ->missing('items.1.imei1'));

    $payload = json_encode($response->viewData('page'));

    expect($payload)
        ->not->toContain(StoreFixture::imei(1))
        ->not->toContain('unit_cost')
        ->not->toContain('max_discount');
});

it('never mixes in another store', function () {
    $code = openStorefront($this->store);

    $other = StoreFixture::create();
    $other->receivePhone(7, ['sale_price' => '9990000']);
    app(TenantContext::class)->forget();

    $this->get("/vitrina/{$code}")
        ->assertInertia(fn ($page) => $page->has('items', 2));

    expect(json_encode($this->get("/vitrina/{$code}")->viewData('page')))->not->toContain('9 990 000');
});

it('lets only the owner open or close it', function () {
    $seller = $this->store->seller();

    $this->actingAs($seller)->put(route('store-settings.storefront'), ['enabled' => 1])->assertForbidden();
    expect(app(PublicCatalog::class)->isEnabled($this->store->tenant->refresh()))->toBeFalse();

    $this->actingAs($this->store->owner)->put(route('store-settings.storefront'), ['enabled' => 1])->assertSessionHasNoErrors();
    expect(app(PublicCatalog::class)->isEnabled($this->store->tenant->refresh()))->toBeTrue();

    app(TenantContext::class)->forget();
    $this->actingAs($this->store->owner)->get(route('store-settings.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('storefront.enabled', true)
            ->where('storefront.url', fn ($url) => str_contains((string) $url, '/vitrina/')));
});

it('sends the mini app button from the shop link in the bot', function () {
    config([
        'services.telegram.token' => 'test-token',
        'services.telegram.username' => 'phonetrade_test_bot',
        'services.telegram.webhook_secret' => 'hook-secret',
    ]);
    Bus::fake([SendTelegramMessage::class]);
    $code = openStorefront($this->store);

    $send = fn (string $text) => $this->postJson('/api/telegram/webhook', [
        'message' => ['chat' => ['id' => 777, 'type' => 'private'], 'from' => ['id' => 777], 'text' => $text],
    ], ['X-Telegram-Bot-Api-Secret-Token' => 'hook-secret'])->assertOk();

    $send("/start shop_{$code}");

    Bus::assertDispatched(SendTelegramMessage::class, fn (SendTelegramMessage $job) => $job->chatId === 777
        && str_ends_with((string) data_get($job->replyMarkup, 'inline_keyboard.0.0.web_app.url'), "/vitrina/{$code}"));

    $send('/start shop_zzzzzzzzzzzz');

    Bus::assertDispatched(SendTelegramMessage::class, fn (SendTelegramMessage $job) => str_contains($job->text, 'yopiq'));
});
