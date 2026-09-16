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

    public function test_deleting_an_activity_note_removes_it_from_the_whole_order(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $order = $this->makeOrder('ملاحظة للحذف');
        $note = WorkerOrderNote::query()->create([
            'order_id' => $order->id,
            'user_id' => $admin->id,
            'body' => 'ملاحظة للحذف',
        ]);

        $receipt = $order->paymentReceipts()->create([
            'receipt_number' => 'RCP-TEST-0001',
            'amount' => 100,
            'total_amount' => 500,
            'amount_paid_before' => 0,
            'amount_paid_after' => 100,
            'remaining_after' => 400,
            'payment_method' => 'cash',
            'type' => 'payment',
            'approval_status' => 'pending',
            'notes' => "سند قبض عند إنشاء الطلب\nملاحظة للحذف",
        ]);

        $this->actingAs($admin)
            ->from(route('orders.show', $order))
            ->delete(route('orders.notes.destroy', [$order, $note]))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseMissing('worker_order_notes', [
            'id' => $note->id,
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'notes' => null,
        ]);
        $this->assertSame('سند قبض عند إنشاء الطلب', $receipt->fresh()->notes);
        $this->assertFalse(
            \App\Models\OrderLog::query()
                ->where('order_id', $order->id)
                ->get()
                ->contains(fn ($log) => str_contains(json_encode($log->changes) ?: '', 'ملاحظة للحذف')),
        );
    }

    public function test_deleted_notes_are_removed_from_approved_and_rejected_receipts(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $order = $this->makeOrder('ملاحظة للحذف');

        $approved = $order->paymentReceipts()->create([
            'receipt_number' => 'RCP-TEST-APPROVED',
            'amount' => 100,
            'total_amount' => 500,
            'amount_paid_before' => 0,
            'amount_paid_after' => 100,
            'remaining_after' => 400,
            'payment_method' => 'cash',
            'type' => 'payment',
            'approval_status' => 'approved',
            'approved_at' => now(),
            'notes' => 'ملاحظة للحذف',
        ]);
        $rejected = $order->paymentReceipts()->create([
            'receipt_number' => 'RCP-TEST-REJECTED',
            'amount' => 50,
            'total_amount' => 500,
            'amount_paid_before' => 100,
            'amount_paid_after' => 100,
            'remaining_after' => 400,
            'payment_method' => 'cash',
            'type' => 'payment',
            'approval_status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => 'مرفوض للتجربة',
            'notes' => 'ملاحظة للحذف',
        ]);
        $noon = $order->paymentReceipts()->create([
            'receipt_number' => 'RCP-TEST-NOON',
            'amount' => 80,
            'total_amount' => 500,
            'amount_paid_before' => 100,
            'amount_paid_after' => 180,
            'remaining_after' => 320,
            'payment_method' => 'noon',
            'type' => 'payment',
            'approval_status' => 'approved',
            'approved_at' => now(),
            'notes' => "دفع إلكتروني عبر Noon (noon-123)\nملاحظة للحذف",
        ]);

        $this->actingAs($admin)
            ->from(route('orders.show', $order))
            ->delete(route('orders.notes.destroy-field', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'notes' => null,
        ]);
        $this->assertNull($approved->fresh()->notes);
        $this->assertNull($rejected->fresh()->notes);
        $this->assertSame('دفع إلكتروني عبر Noon (noon-123)', $noon->fresh()->notes);
    }

    public function test_deleting_the_order_notes_field_also_deletes_matching_activity_notes(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $order = $this->makeOrder('ملاحظة الطلب الأصلية');
        WorkerOrderNote::query()->create([
            'order_id' => $order->id,
            'user_id' => $admin->id,
            'body' => 'ملاحظة الطلب الأصلية',
        ]);
        WorkerOrderNote::query()->create([
            'order_id' => $order->id,
            'user_id' => $admin->id,
            'body' => 'ملاحظة مختلفة تبقى',
        ]);

        $this->actingAs($admin)
            ->from(route('orders.show', $order))
            ->delete(route('orders.notes.destroy-field', $order))
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'notes' => null,
        ]);
        $this->assertDatabaseMissing('worker_order_notes', [
            'order_id' => $order->id,
            'body' => 'ملاحظة الطلب الأصلية',
        ]);
        $this->assertDatabaseHas('worker_order_notes', [
            'order_id' => $order->id,
            'body' => 'ملاحظة مختلفة تبقى',
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
