<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\InstallmentRate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function payload(): array
    {
        return [
            'name' => 'Smart Mobile',
            'branch_name' => 'Chilonzor filiali',
            'owner_name' => 'Anvar Karimov',
            'owner_phone' => '+998 90 111 22 33',
            'owner_password' => 'secret-123',
        ];
    }

    public function test_superadmin_is_sent_to_the_tenant_list(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('admin.tenants.index'));
        $this->actingAs($admin)->get(route('admin.tenants.index'))->assertOk();
    }

    public function test_superadmin_can_open_a_store(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.tenants.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.tenants.index'));

        $tenant = Tenant::query()->where('name', 'Smart Mobile')->firstOrFail();
        $owner = User::query()->where('phone', '998901112233')->firstOrFail();
        $branch = Branch::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame($tenant->id, $owner->tenant_id);
        $this->assertSame(Role::Owner, $owner->role);
        $this->assertSame('Chilonzor filiali', $branch->name);

        // Egasida 3 ta admin kassa, filialda 3 ta kassa.
        $this->assertSame(3, CashAccount::query()->where('tenant_id', $tenant->id)->whereNull('branch_id')->count());
        $this->assertSame(3, CashAccount::query()->where('branch_id', $branch->id)->count());

        $this->assertSame(
            [3 => '20.00', 6 => '35.00', 9 => '45.00', 12 => '55.00'],
            InstallmentRate::query()->where('branch_id', $branch->id)->orderBy('months')->pluck('markup_pct', 'months')->all(),
        );

        $this->assertSame('10000', $tenant->setting('round_step_uzs'));
        $this->assertTrue(AuditLog::query()->where('action', 'tenant.created')->exists());
    }

    public function test_new_owner_can_log_in(): void
    {
        $admin = User::factory()->superadmin()->create();
        $this->actingAs($admin)->post(route('admin.tenants.store'), $this->payload());
        $this->post(route('logout'));

        $this->post(route('login.store'), ['phone' => '90 111 22 33', 'password' => 'secret-123']);

        $this->assertAuthenticated();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_owner_phone_must_be_unique_across_all_stores(): void
    {
        $admin = User::factory()->superadmin()->create();
        User::factory()->create(['phone' => '998901112233']);

        $this->actingAs($admin)
            ->post(route('admin.tenants.store'), $this->payload())
            ->assertSessionHasErrors('owner_phone');

        $this->assertFalse(Tenant::query()->where('name', 'Smart Mobile')->exists());
    }

    public function test_store_users_cannot_open_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.tenants.index'))->assertForbidden();
        $this->actingAs(User::factory()->seller()->create())->post(route('admin.tenants.store'), $this->payload())->assertForbidden();
    }

    public function test_superadmin_cannot_open_store_settings(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)->get(route('branches.index'))->assertForbidden();
    }

    public function test_users_of_a_blocked_store_are_logged_out(): void
    {
        $admin = User::factory()->superadmin()->create();
        $owner = User::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.tenants.status', $owner->tenant_id), ['status' => TenantStatus::Blocked->value])
            ->assertRedirect(route('admin.tenants.index'));

        $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_deactivated_user_is_logged_out(): void
    {
        $seller = User::factory()->seller()->inactive()->create();

        $this->actingAs($seller)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
