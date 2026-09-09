<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_redirected_from_order_logs(): void
    {
        $this->get(route('order-logs.index'))
            ->assertRedirect(route('login'));
    }

    public function test_accounts_role_cannot_open_order_logs(): void
    {
        $accounts = User::factory()->staff(User::ROLE_ACCOUNTS)->create();

        $this->actingAs($accounts)
            ->get(route('order-logs.index'))
            ->assertRedirect(route('quotations.index'));
    }

    public function test_creating_an_order_records_the_authenticated_user(): void
    {
        $admin = User::factory()->admin()->create([
            'customer_name' => 'أحمد المنشئ',
        ]);

        $this->actingAs($admin);
        $order = $this->makeOrder('عميل تجريبي', 500);

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'action' => OrderLog::ACTION_CREATED,
            'user_id' => $admin->id,
            'user_name' => 'أحمد المنشئ',
        ]);
    }

    public function test_updating_an_order_records_who_and_when(): void
    {
        $admin = User::factory()->admin()->create([
            'customer_name' => 'سارة المعدّلة',
        ]);

        $this->actingAs($admin);
        $order = $this->makeOrder('عميل تجريبي', 500);

        $this->actingAs($admin);
        $order->update(['customer_name' => 'عميل بعد التعديل']);

        $this->assertDatabaseHas('order_logs', [
            'order_id' => $order->id,
            'action' => OrderLog::ACTION_UPDATED,
            'user_id' => $admin->id,
            'user_name' => 'سارة المعدّلة',
        ]);
    }

    public function test_notification_only_updates_are_not_logged_as_edits(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $order = $this->makeOrder('عميل تجريبي', 500);

        $order->update(['whatsapp_notified_at' => now()]);

        $this->assertSame(1, OrderLog::query()->where('order_id', $order->id)->count());
        $this->assertDatabaseMissing('order_logs', [
            'order_id' => $order->id,
            'action' => OrderLog::ACTION_UPDATED,
        ]);
    }

    public function test_unauthenticated_updates_are_not_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $order = $this->makeOrder('عميل تجريبي', 500);
        auth()->logout();

        $order->update(['notes' => 'تحديث بدون مستخدم']);

        $this->assertDatabaseMissing('order_logs', [
            'order_id' => $order->id,
            'action' => OrderLog::ACTION_UPDATED,
        ]);
    }

    public function test_admin_can_see_creator_and_editor_on_order_logs_page(): void
    {
        $admin = User::factory()->admin()->create([
            'customer_name' => 'مدير النظام',
        ]);

        $this->actingAs($admin);
        $order = $this->makeOrder('لمي الصغير', 1950);
        $order->update(['address' => 'الرياض']);

        $this->actingAs($admin)
            ->get(route('order-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('OrderLogs/Index')
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', $order->order_number)
                ->where('orders.data.0.customer_name', 'لمي الصغير')
                ->where('orders.data.0.created_by.name', 'مدير النظام')
                ->where('orders.data.0.updated_by.name', 'مدير النظام')
                ->where('stats.all', 1)
                ->where('stats.edited', 1)
            );
    }

    private function makeOrder(string $customerName, float $total): Order
    {
        return Order::query()->create([
            'customer_name' => $customerName,
            'order_number' => Order::generateOrderNumber(),
            'total_amount' => $total,
            'amount_paid' => 0,
            'currency' => 'SAR',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'bank_transfer',
        ]);
    }
}
