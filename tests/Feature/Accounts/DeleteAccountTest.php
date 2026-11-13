<?php

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\StoreFixture;

beforeEach(function () {
    $this->store = StoreFixture::create();
    $this->seller = $this->store->seller();
    app(TenantContext::class)->forget();
});

it('deletes a seller account from the web after the password is confirmed', function () {
    $phone = $this->seller->phone;

    $this->actingAs($this->seller)->delete(route('account.destroy'), ['password' => 'wrong'])->assertSessionHasErrors('password');
    expect($this->seller->refresh()->is_active)->toBeTrue();

    $this->actingAs($this->seller)->delete(route('account.destroy'), ['password' => 'password'])->assertRedirect(route('login'));

    $deleted = $this->seller->refresh();
    expect($deleted->is_active)->toBeFalse()
        ->and($deleted->name)->toBe("O'chirilgan xodim")
        ->and($deleted->phone)->not->toBe($phone);
    $this->assertGuest();

    // Bo'shagan raqam bilan yangi xodim ochish mumkin.
    expect(User::query()->where('phone', $phone)->exists())->toBeFalse();
});

it('deletes a seller account through the api and revokes its tokens', function () {
    $token = $this->seller->createToken('Pixel')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/account', ['password' => 'nope'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_FAILED');

    $this->withToken($token)->deleteJson('/api/v1/account', ['password' => 'password'])
        ->assertOk()
        ->assertJsonPath('status', 'deleted');

    expect($this->seller->refresh()->is_active)->toBeFalse()
        ->and($this->seller->tokens()->count())->toBe(0);
});

it('turns an owner deletion into a request that can be cancelled', function () {
    Sanctum::actingAs($this->store->owner);

    $this->deleteJson('/api/v1/account', ['password' => 'password'])
        ->assertOk()
        ->assertJsonPath('status', 'requested');

    expect($this->store->tenant->refresh()->deletion_requested_at)->not->toBeNull()
        ->and($this->store->owner->refresh()->is_active)->toBeTrue();

    app(TenantContext::class)->forget();
    $this->actingAs($this->store->owner)->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('store.deletion_requested_at', now()->format('d.m.Y')));

    $this->actingAs($this->store->owner)->post(route('account.cancel-deletion'))->assertSessionHasNoErrors();
    expect($this->store->tenant->refresh()->deletion_requested_at)->toBeNull();

    $this->actingAs($this->seller)->post(route('account.cancel-deletion'))->assertForbidden();
});

it('still allows deletion when the subscription has expired', function () {
    $this->store->tenant->forceFill(['paid_until' => now()->subMonths(2)->toDateString()])->save();

    $this->actingAs($this->seller)->delete(route('account.destroy'), ['password' => 'password'])->assertRedirect(route('login'));

    expect($this->seller->refresh()->is_active)->toBeFalse();
});

it('lets the superadmin purge a store with everything in it', function () {
    Storage::fake('local');
    $admin = User::factory()->superadmin()->create();
    $tenant = $this->store->tenant;

    app(TenantContext::class)->runFor($tenant, function () use ($tenant) {
        $this->store->receivePhone(1);
        $customer = Customer::query()->create(['full_name' => 'Aliyev Jasur', 'phone' => '998901234567']);
        Storage::disk('local')->put("customers/{$tenant->id}/{$customer->id}/passport.jpg", 'x');
    });

    $other = StoreFixture::create();
    app(TenantContext::class)->forget();

    $this->actingAs($admin)->delete(route('admin.tenants.destroy', $tenant), ['confirm' => 'boshqa nom'])->assertSessionHasErrors('confirm');
    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeTrue();

    $this->actingAs($admin)->delete(route('admin.tenants.destroy', $tenant), ['confirm' => $tenant->name])->assertRedirect(route('admin.tenants.index'));

    expect(Tenant::query()->whereKey($tenant->id)->exists())->toBeFalse()
        ->and(User::query()->where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(Customer::query()->where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(Sale::query()->where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles("customers/{$tenant->id}"))->toBe([])
        // Boshqa do'konga tegilmaydi.
        ->and(Tenant::query()->whereKey($other->tenant->id)->exists())->toBeTrue()
        ->and(User::query()->where('tenant_id', $other->tenant->id)->count())->toBe(1);
});

it('keeps store deletion away from store users', function () {
    $this->actingAs($this->store->owner)
        ->delete(route('admin.tenants.destroy', $this->store->tenant), ['confirm' => $this->store->tenant->name])
        ->assertForbidden();

    expect(Tenant::query()->whereKey($this->store->tenant->id)->exists())->toBeTrue();
});

it('serves the public privacy and deletion pages', function () {
    config(['app.contact_email' => 'support@example.test']);

    $this->get('/privacy')->assertOk()->assertInertia(fn ($page) => $page->component('legal/privacy')->where('contactEmail', 'support@example.test'));
    $this->get('/account-deletion')->assertOk()->assertInertia(fn ($page) => $page->component('legal/account-deletion')->where('fulfilDays', 30));
});
