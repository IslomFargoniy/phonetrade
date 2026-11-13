<?php

use App\Actions\Sales\CloseSale;
use App\Models\StockUnitPhoto;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\StoreFixture;

beforeEach(function () {
    Storage::fake('public');
    $this->store = StoreFixture::create();
    $this->unit = $this->store->receivePhone(1);
    $this->other = $this->store->receivePhone(2);
    app(TenantContext::class)->forget();
});

it('stores several photos per phone, resized to square jpegs', function () {
    $this->actingAs($this->store->owner)
        ->post(route('stock.units.photos.store', $this->unit), [
            'photos' => [UploadedFile::fake()->image('front.png', 1600, 1200), UploadedFile::fake()->image('back.jpg', 600, 900)],
        ])
        ->assertSessionHasNoErrors();

    $photos = StockUnitPhoto::query()->where('stock_unit_id', $this->unit->id)->orderBy('sort')->get();
    expect($photos)->toHaveCount(2);

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($photos[0]->path));
    expect([$width, $height])->toBe([1080, 1080])
        ->and(str_ends_with($photos[0]->path, '.jpg'))->toBeTrue();

    // Boshqa telefonda rasm yo'q — har bir telefonning rasmi o'ziniki.
    expect(StockUnitPhoto::query()->where('stock_unit_id', $this->other->id)->count())->toBe(0);
});

it('shows the phone photo first, then the model image, everywhere stock is listed', function () {
    $this->store->phone->forceFill(['image_path' => 'products/model.jpg'])->save();
    app(TenantContext::class)->runFor($this->store->tenant, fn () => $this->unit->photos()->create(['path' => 'units/own.jpg', 'sort' => 1]));
    app(TenantContext::class)->forget();

    $this->actingAs($this->store->owner)->get(route('stock.index'))
        ->assertInertia(fn ($page) => $page
            ->where('units.0.image_url', fn ($url) => str_ends_with((string) $url, 'products/model.jpg'))
            ->where('units.1.image_url', fn ($url) => str_ends_with((string) $url, 'units/own.jpg'))
            ->has('units.1.photos', 1));

    Sanctum::actingAs($this->store->owner);
    $this->getJson('/api/v1/stock/lookup?code='.$this->unit->imei1)
        ->assertJsonPath('units.0.photos.0.url', fn ($url) => str_ends_with((string) $url, 'units/own.jpg'));
});

it('keeps the sold phone photo on the sale', function () {
    app(TenantContext::class)->runFor($this->store->tenant, function () {
        $this->unit->photos()->create(['path' => 'units/sold.jpg', 'sort' => 1]);

        return app(CloseSale::class)->handle($this->store->branch, $this->store->owner, [['stock_unit_id' => $this->unit->id]], [
            ['method' => 'cash', 'currency' => 'UZS', 'amount' => '4850000'],
        ]);
    });
    app(TenantContext::class)->forget();

    $this->actingAs($this->store->owner)->get(route('sales.index'))
        ->assertInertia(fn ($page) => $page->where('sales.0.image_url', fn ($url) => str_ends_with((string) $url, 'units/sold.jpg')));
});

it('lets a seller manage photos only in their own branch, also through the api', function () {
    $seller = app(TenantContext::class)->runFor($this->store->tenant, fn () => $this->store->seller());
    app(TenantContext::class)->forget();

    Sanctum::actingAs($seller);
    $response = $this->postJson("/api/v1/stock/units/{$this->unit->id}/photos", ['photos' => [UploadedFile::fake()->image('a.jpg')]])
        ->assertCreated()
        ->assertJsonCount(1, 'photos');

    $this->deleteJson('/api/v1/stock/photos/'.$response->json('photos.0.id'))->assertOk()->assertJsonCount(0, 'photos');

    // Boshqa do'konning telefoni.
    $foreign = StoreFixture::create()->receivePhone(9);
    app(TenantContext::class)->forget();
    Sanctum::actingAs($seller);
    $this->postJson("/api/v1/stock/units/{$foreign->id}/photos", ['photos' => [UploadedFile::fake()->image('a.jpg')]])->assertNotFound();
});

it('limits photos per phone and rejects non images', function () {
    $this->actingAs($this->store->owner)
        ->post(route('stock.units.photos.store', $this->unit), ['photos' => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 9))])
        ->assertSessionHasErrors('photos');

    $this->actingAs($this->store->owner)
        ->post(route('stock.units.photos.store', $this->unit), ['photos' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')]])
        ->assertSessionHasErrors('photos.0');
});

it('lets the owner set and remove the model image', function () {
    $this->actingAs($this->store->owner)
        ->post(route('catalog.products.image', $this->store->phone), ['image' => UploadedFile::fake()->image('model.png', 300, 600)])
        ->assertSessionHasNoErrors();

    $path = $this->store->phone->refresh()->image_path;
    expect($path)->not->toBeNull();
    Storage::disk('public')->assertExists($path);

    $this->actingAs($this->store->owner)->delete(route('catalog.products.image.destroy', $this->store->phone));
    expect($this->store->phone->refresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});
