<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\WorkerOrderNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_add_a_note_on_the_order_page(): void
    {
        $admin = User::factory()->admin()->create([
            'customer_name' => 'أحمد',
        ]);
        $order = $this->makeOrder();

        $this->actingAs($admin)
            ->from(route('orders.show', $order))
            ->post(route('orders.notes.store', $order), [
                'body' => 'ملاحظة جديدة على الطلب',
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('worker_order_notes', [
            'order_id' => $order->id,
            'user_id' => $admin->id,
            'body' => 'ملاحظة جديدة على الطلب',
        ]);
    }

    public function test_admin_can_delete_an_added_note(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder();
        $note = WorkerOrderNote::query()->create([
            'order_id' => $order->id,
            'user_id' => $admin->id,
            'body' => 'ملاحظة للحذف',
        ]);

        $this->actingAs($admin)
            ->from(route('orders.show', $order))
            ->delete(route('orders.notes.destroy', [$order, $note]))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseMissing('worker_order_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_admin_can_delete_the_order_notes_field(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder('ملاحظة الطلب الأصلية');

        $this->actingAs($admin)
            ->from(route('orders.show', $order))
            ->delete(route('orders.notes.destroy-field', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'notes' => null,
        ]);
    }

    public function test_note_from_another_order_cannot_be_deleted_here(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder();
        $other = $this->makeOrder();
        $note = WorkerOrderNote::query()->create([
            'order_id' => $other->id,
            'user_id' => $admin->id,
            'body' => 'ملاحظة طلب آخر',
        ]);

        $this->actingAs($admin)
            ->delete(route('orders.notes.destroy', [$order, $note]))
            ->assertNotFound();

        $this->assertDatabaseHas('worker_order_notes', [
            'id' => $note->id,
        ]);
    }

    private function makeOrder(?string $notes = null): Order
    {
        return Order::query()->create([
            'customer_name' => 'عميل تجريبي',
            'order_number' => Order::generateOrderNumber(),
            'total_amount' => 500,
            'amount_paid' => 0,
            'currency' => 'SAR',
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'bank_transfer',
            'notes' => $notes,
        ]);
    }
}
