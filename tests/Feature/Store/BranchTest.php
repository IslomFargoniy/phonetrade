<?php

namespace Tests\Feature\Store;

use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\InstallmentRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_a_branch_with_cash_accounts_and_rates(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->post(route('branches.store'), ['name' => 'Yunusobod', 'address' => 'Amir Temur 1', 'phone' => '71 200 00 00'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('branches.index'));

        $branch = Branch::query()->where('name', 'Yunusobod')->firstOrFail();

        $this->assertSame($owner->tenant_id, $branch->tenant_id);
        $this->assertSame('998712000000', $branch->phone);
        $this->assertSame(3, CashAccount::query()->where('branch_id', $branch->id)->count());
        $this->assertSame(4, InstallmentRate::query()->where('branch_id', $branch->id)->count());
    }

    public function test_branch_name_is_unique_within_a_store_only(): void
    {
        $owner = User::factory()->create();
        Branch::factory()->create(['tenant_id' => $owner->tenant_id, 'name' => 'Chilonzor']);
        Branch::factory()->create(['name' => 'Sergeli']);

        $this->actingAs($owner)->post(route('branches.store'), ['name' => 'Chilonzor'])->assertSessionHasErrors('name');
        $this->actingAs($owner)->post(route('branches.store'), ['name' => 'Sergeli'])->assertSessionHasNoErrors();
    }

    public function test_last_active_branch_cannot_be_deactivated(): void
    {
        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $owner->tenant_id]);

        $this->actingAs($owner)
            ->patch(route('branches.update', $branch), ['name' => $branch->name, 'is_active' => false])
            ->assertSessionHasErrors('is_active');

        $this->assertTrue($branch->fresh()->is_active);
    }

    public function test_seller_cannot_manage_branches(): void
    {
        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)->get(route('branches.index'))->assertForbidden();
        $this->actingAs($seller)->post(route('branches.store'), ['name' => 'Yangi'])->assertForbidden();
    }

    public function test_seller_can_switch_only_to_assigned_branches(): void
    {
        $seller = User::factory()->seller()->create();
        $assigned = Branch::factory()->create(['tenant_id' => $seller->tenant_id]);
        $other = Branch::factory()->create(['tenant_id' => $seller->tenant_id]);
        $seller->branches()->attach($assigned);

        $this->actingAs($seller)->post(route('branches.select', $assigned))->assertSessionHas('branch_id', $assigned->id);
        $this->actingAs($seller)->post(route('branches.select', $other))->assertForbidden();
    }
}
