<?php

namespace Tests\Feature\Store;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_a_seller(): void
    {
        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $owner->tenant_id]);

        $this->actingAs($owner)
            ->post(route('staff.store'), [
                'name' => 'Sardor',
                'phone' => '+998 93 555 66 77',
                'password' => 'seller-123',
                'branch_ids' => [$branch->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('staff.index'));

        $seller = User::query()->where('phone', '998935556677')->firstOrFail();

        $this->assertSame(Role::Seller, $seller->role);
        $this->assertSame($owner->tenant_id, $seller->tenant_id);
        $this->assertTrue($seller->canAccessBranch($branch));
    }

    public function test_seller_cannot_be_assigned_to_another_stores_branch(): void
    {
        $owner = User::factory()->create();
        $foreignBranch = Branch::factory()->create();

        $this->actingAs($owner)
            ->post(route('staff.store'), [
                'name' => 'Sardor',
                'phone' => '93 555 66 77',
                'password' => 'seller-123',
                'branch_ids' => [$foreignBranch->id],
            ])
            ->assertSessionHasErrors('branch_ids.0');
    }

    public function test_owner_can_update_and_deactivate_a_seller(): void
    {
        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $owner->tenant_id]);
        $seller = User::factory()->seller()->create(['tenant_id' => $owner->tenant_id]);

        $this->actingAs($owner)
            ->patch(route('staff.update', $seller), [
                'name' => 'Yangi ism',
                'phone' => $seller->phone,
                'password' => '',
                'is_active' => false,
                'branch_ids' => [$branch->id],
            ])
            ->assertSessionHasNoErrors();

        $seller->refresh();

        $this->assertSame('Yangi ism', $seller->name);
        $this->assertFalse($seller->is_active);
        $this->assertTrue(Hash::check('password', $seller->password));
    }

    public function test_owner_account_cannot_be_edited_through_staff(): void
    {
        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $owner->tenant_id]);

        $this->actingAs($owner)
            ->patch(route('staff.update', $owner), [
                'name' => 'X', 'phone' => $owner->phone, 'branch_ids' => [$branch->id],
            ])
            ->assertForbidden();
    }

    public function test_seller_of_another_store_is_not_reachable(): void
    {
        $owner = User::factory()->create();
        $branch = Branch::factory()->create(['tenant_id' => $owner->tenant_id]);
        $foreignSeller = User::factory()->seller()->create();

        $this->actingAs($owner)
            ->patch(route('staff.update', $foreignSeller), [
                'name' => 'X', 'phone' => $foreignSeller->phone, 'branch_ids' => [$branch->id],
            ])
            ->assertNotFound();
    }

    public function test_seller_cannot_manage_staff(): void
    {
        $this->actingAs(User::factory()->seller()->create())->get(route('staff.index'))->assertForbidden();
    }
}
