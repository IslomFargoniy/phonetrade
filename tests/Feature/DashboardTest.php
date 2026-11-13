<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_each_role_lands_on_its_own_workplace()
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertRedirect(route('reports.index'));
        $this->actingAs(User::factory()->seller()->create())->get(route('dashboard'))->assertRedirect(route('pos.index'));
        $this->actingAs(User::factory()->superadmin()->create())->get(route('dashboard'))->assertRedirect(route('admin.tenants.index'));
    }
}
