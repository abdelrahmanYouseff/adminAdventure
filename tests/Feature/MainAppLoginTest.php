<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MainAppLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_warehouse_keeper_can_log_in_from_the_main_app(): void
    {
        $keeper = User::factory()->staff(User::ROLE_WAREHOUSE_KEEPER)->create([
            'email' => 'keeper@example.com',
        ]);

        $this->post(route('main.login.store'), [
            'login' => 'keeper@example.com',
            'password' => 'password',
        ])->assertRedirect(route('warehouse.index'));

        $this->assertAuthenticatedAs($keeper);
    }

    public function test_workers_manager_still_enters_the_main_app(): void
    {
        User::factory()->staff(User::ROLE_WORKERS_MANAGER)->create([
            'email' => 'manager@example.com',
        ]);

        $this->post(route('main.login.store'), [
            'login' => 'manager@example.com',
            'password' => 'password',
        ])->assertRedirect('/main-app');

        $this->assertAuthenticated();
    }
}
