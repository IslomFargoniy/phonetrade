<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'phone' => '+998 90 123 45 67',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('998901234567', $user->phone);
    }

    public function test_phone_must_be_unique()
    {
        User::factory()->create(['phone' => '998901234567']);
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'phone' => '90 123 45 67',
            ]);

        $response
            ->assertSessionHasErrors('phone')
            ->assertRedirect(route('profile.edit'));
    }

    public function test_phone_must_be_a_valid_number()
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'phone' => '12345',
            ])
            ->assertSessionHasErrors('phone');
    }

    public function test_account_cannot_be_deleted_by_its_owner()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/settings/profile')->assertStatus(405);

        $this->assertNotNull($user->fresh());
    }
}
