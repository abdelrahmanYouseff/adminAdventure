<?php

namespace Tests\Feature;

use App\Http\Controllers\ImpersonationController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_impersonate_a_staff_user(): void
    {
        $admin = User::factory()->admin()->create();
        $worker = User::factory()->staff(User::ROLE_WORKER)->create();

        $this->actingAs($admin)
            ->post(route('users.impersonate', $worker))
            ->assertRedirect(route('pwa.dashboard'));

        $this->assertAuthenticatedAs($worker);
        $this->assertSame($admin->id, session(ImpersonationController::SESSION_KEY));
    }

    public function test_admin_can_stop_impersonation(): void
    {
        $admin = User::factory()->admin()->create();
        $accounts = User::factory()->staff(User::ROLE_ACCOUNTS)->create();

        $this->actingAs($admin)
            ->post(route('users.impersonate', $accounts));

        $this->post(route('impersonation.stop'))
            ->assertRedirect(route('users'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session(ImpersonationController::SESSION_KEY));
    }

    public function test_manager_cannot_impersonate(): void
    {
        $manager = User::factory()->staff(User::ROLE_MANAGER)->create();
        $worker = User::factory()->staff(User::ROLE_WORKER)->create();

        $this->actingAs($manager)
            ->from(route('users'))
            ->post(route('users.impersonate', $worker))
            ->assertRedirect(route('users'));

        $this->assertAuthenticatedAs($manager);
        $this->assertNull(session(ImpersonationController::SESSION_KEY));
    }

    public function test_admin_cannot_impersonate_themself(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('users'))
            ->post(route('users.impersonate', $admin))
            ->assertRedirect(route('users'));

        $this->assertAuthenticatedAs($admin);
        $this->assertNull(session(ImpersonationController::SESSION_KEY));
    }
}
