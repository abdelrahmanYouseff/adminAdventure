<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_new_assigned_installation_is_not_hidden_by_older_orders(): void
    {
        $worker = User::factory()->staff(User::ROLE_WORKER)->create();

        for ($i = 0; $i < 60; $i++) {
            $this->makeAssignedInstallation(
                $worker,
                'Old customer '.$i,
                now()->subDays(200 + $i)->toDateString(),
                sprintf('ORD-OLD-%04d', $i + 1),
            );
        }

        $visible = $this->makeAssignedInstallation(
            $worker,
            'New customer',
            now()->toDateString(),
            'ORD-NEW-0001',
        );

        $this->actingAs($worker)
            ->get(route('pwa.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('installations', 61)
                ->where('installations', fn ($items) => collect($items)->contains(
                    fn ($item) => (int) $item['id'] === $visible->id
                        && $item['task_type'] === 'installation'
                        && $item['list_status'] === 'current'
                ))
                ->where('installations', fn ($items) => collect($items)->contains(
                    fn ($item) => $item['customer_name'] === 'Old customer 0'
                ))
            );
    }

    public function test_each_worker_only_sees_their_own_assigned_current_orders(): void
    {
        $firstWorker = User::factory()->staff(User::ROLE_WORKER)->create();
        $secondWorker = User::factory()->staff(User::ROLE_WORKER)->create();

        $firstOrder = $this->makeAssignedInstallation(
            $firstWorker,
            'First worker job',
            now()->toDateString(),
            'ORD-W1-0001',
        );
        $secondOrder = $this->makeAssignedInstallation(
            $secondWorker,
            'Second worker job',
            now()->toDateString(),
            'ORD-W2-0001',
        );

        $this->actingAs($firstWorker)
            ->get(route('pwa.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('installations', 1)
                ->where('installations.0.id', $firstOrder->id)
            );

        $this->actingAs($secondWorker)
            ->get(route('pwa.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('installations', 1)
                ->where('installations.0.id', $secondOrder->id)
            );
    }

    public function test_blank_task_type_still_creates_an_installation_card(): void
    {
        $worker = User::factory()->staff(User::ROLE_WORKER)->create();

        $order = $this->makeAssignedInstallation(
            $worker,
            'Blank task type',
            now()->toDateString(),
            taskType: '',
        );

        $this->actingAs($worker)
            ->get(route('pwa.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('installations', fn ($items) => collect($items)->contains(
                    fn ($item) => (int) $item['id'] === $order->id
                        && $item['task_type'] === 'installation'
                ))
            );
    }

    private function makeAssignedInstallation(
        User $worker,
        string $customerName,
        string $activityDate,
        ?string $orderNumber = null,
        ?string $taskType = WorkerOrderAssembler::TYPE_INSTALLATION,
    ): Order {
        $order = Order::query()->create([
            'user_id' => $worker->id,
            'customer_name' => $customerName,
            'customer_phone' => '0500000000',
            'order_number' => $orderNumber ?? Order::generateOrderNumber(),
            'total_amount' => 100,
            'amount_paid' => 0,
            'currency' => 'SAR',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'bank_transfer',
            'activity_date' => $activityDate,
        ]);

        WorkerOrder::query()->create([
            'order_id' => $order->id,
            'line_index' => 0,
            'product_name' => 'منتج',
            'customer_name' => $customerName,
            'installation_date' => $activityDate,
            'status' => 'pending',
        ]);

        WorkerOrderAssembler::query()->create([
            'order_id' => $order->id,
            'worker_order_id' => null,
            'worker_name' => $worker->name,
            'task_type' => $taskType,
            'user_id' => $worker->id,
        ]);

        return $order;
    }
}
