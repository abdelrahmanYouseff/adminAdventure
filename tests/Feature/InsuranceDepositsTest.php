<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Support\InsuranceApprovalChain;
use App\Support\MediaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InsuranceDepositsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_all_staff_except_workers_can_open_the_page(): void
    {
        $order = $this->makeEligibleOrder();

        foreach ([
            User::ROLE_ADMIN,
            User::ROLE_GENERAL_MANAGER,
            User::ROLE_MANAGER,
            User::ROLE_ACCOUNTS,
            User::ROLE_WORKERS_MANAGER,
            User::ROLE_WAREHOUSE_KEEPER,
        ] as $role) {
            $staff = User::factory()->staff($role)->create();

            $this->actingAs($staff)
                ->get(route('insurance-deposits.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('InsuranceDeposits/Index')
                    ->has('deposits.data', 1)
                    ->where('deposits.data.0.id', $order->id)
                    ->where('deposits.data.0.waiting_on_label', 'مدير العمال')
                );
        }
    }

    public function test_workers_cannot_open_insurance_deposits(): void
    {
        $this->makeEligibleOrder();
        $worker = User::factory()->staff(User::ROLE_WORKER)->create();

        $this->actingAs($worker)
            ->get(route('insurance-deposits.index'))
            ->assertRedirect(route('pwa.dashboard'));
    }

    public function test_approval_chain_runs_workers_manager_accounts_admin_accounts(): void
    {
        $order = $this->makeEligibleOrder();
        $workersManager = User::factory()->staff(User::ROLE_WORKERS_MANAGER)->create();
        $accounts = User::factory()->staff(User::ROLE_ACCOUNTS)->create();
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->staff(User::ROLE_MANAGER)->create();

        $this->actingAs($accounts)
            ->post(route('insurance-deposits.approve', $order))
            ->assertRedirect();
        $this->assertNull($order->fresh()->insurance_workers_manager_approved_at);

        $this->actingAs($manager)
            ->post(route('insurance-deposits.approve', $order))
            ->assertRedirect();
        $this->assertNull($order->fresh()->insurance_workers_manager_approved_at);

        $this->actingAs($workersManager)
            ->post(route('insurance-deposits.approve', $order))
            ->assertRedirect();
        $order->refresh();
        $this->assertNotNull($order->insurance_workers_manager_approved_at);
        $this->assertSame(InsuranceApprovalChain::STEP_ACCOUNTS_RECEIVED, InsuranceApprovalChain::nextPendingStep($order));

        $this->actingAs($admin)
            ->post(route('insurance-deposits.approve', $order))
            ->assertRedirect();
        $this->assertNull($order->fresh()->insurance_accounts_received_at);

        $this->actingAs($accounts)
            ->post(route('insurance-deposits.approve', $order))
            ->assertRedirect();
        $order->refresh();
        $this->assertNotNull($order->insurance_accounts_received_at);
        $this->assertSame(InsuranceApprovalChain::STEP_ADMIN, InsuranceApprovalChain::nextPendingStep($order));

        $this->actingAs($accounts)
            ->post(route('insurance-deposits.approve', $order))
            ->assertRedirect();
        $this->assertNull($order->fresh()->insurance_admin_approved_at);

        $this->actingAs($admin)
            ->post(route('insurance-deposits.approve', $order))
            ->assertRedirect();
        $order->refresh();
        $this->assertNotNull($order->insurance_admin_approved_at);
        $this->assertSame(InsuranceApprovalChain::STEP_ACCOUNTS_TRANSFER, InsuranceApprovalChain::nextPendingStep($order));

        $this->actingAs($accounts)
            ->post(route('insurance-deposits.approve', $order))
            ->assertRedirect();
        $order->refresh();
        $this->assertTrue(InsuranceApprovalChain::isFullyApproved($order));

        $this->actingAs($accounts)
            ->post(route('insurance-deposits.refund', $order))
            ->assertRedirect();
        $this->assertSame('refunded', $order->fresh()->insurance_status);
    }

    public function test_raising_a_request_requires_a_payment_proof(): void
    {
        $staff = User::factory()->staff(User::ROLE_MANAGER)->create();
        $order = $this->makeOpenOrder($staff);

        $this->actingAs($staff)
            ->post(route('insurance-deposits.store'), [
                'order_id' => $order->id,
                'insurance_amount' => 150,
            ])
            ->assertSessionHasErrors('payment_proof');

        $this->assertNull($order->fresh()->insurance_refund_requested_at);
        $this->assertSame(0, \App\Models\OrderPaymentReceipt::query()->count());
    }

    public function test_raising_a_request_stays_on_the_page_and_does_not_create_a_receipt(): void
    {
        Storage::fake(MediaStorage::DISK);
        $staff = User::factory()->staff(User::ROLE_MANAGER)->create();
        $order = $this->makeOpenOrder($staff);

        $this->actingAs($staff)
            ->post(route('insurance-deposits.store'), [
                'order_id' => $order->id,
                'insurance_amount' => 150,
                'payment_proof' => [UploadedFile::fake()->image('receipt.jpg')],
            ])
            ->assertRedirect(route('insurance-deposits.index', ['status' => 'pending']));

        $order->refresh();
        $this->assertSame(150.0, (float) $order->insurance_amount);
        $this->assertSame('pending', $order->insurance_status);
        $this->assertNotNull($order->insurance_refund_requested_at);
        $this->assertNull($order->insurance_workers_manager_approved_at);
        $this->assertNotEmpty($order->insurance_payment_proof);
        $this->assertSame(0, \App\Models\OrderPaymentReceipt::query()->count());

        $this->actingAs($staff)
            ->get(route('insurance-deposits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InsuranceDeposits/Index')
                ->has('deposits.data', 1)
                ->where('deposits.data.0.id', $order->id)
                ->where('deposits.data.0.waiting_on_label', 'مدير العمال')
                ->has('deposits.data.0.payment_proof_urls', 1)
            );
    }

    public function test_staff_can_add_a_note_visible_to_the_approval_chain(): void
    {
        $order = $this->makeEligibleOrder();
        $workersManager = User::factory()->staff(User::ROLE_WORKERS_MANAGER)->create([
            'customer_name' => 'مدير العمال',
        ]);
        $accounts = User::factory()->staff(User::ROLE_ACCOUNTS)->create([
            'customer_name' => 'المحاسب',
        ]);

        $this->actingAs($workersManager)
            ->post(route('insurance-deposits.notes.store', $order), [
                'body' => 'الصور مكتملة والتركيب سليم',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('worker_order_notes', [
            'order_id' => $order->id,
            'user_id' => $workersManager->id,
            'body' => 'الصور مكتملة والتركيب سليم',
        ]);

        $this->actingAs($accounts)
            ->get(route('insurance-deposits.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InsuranceDeposits/Index')
                ->where('deposits.data.0.notes_count', 1)
                ->where('deposits.data.0.notes.0.body', 'الصور مكتملة والتركيب سليم')
                ->where('deposits.data.0.notes.0.user_name', 'مدير العمال')
                ->where('deposits.data.0.notes.0.user_role', 'مدير العمال')
            );

        $this->actingAs($accounts)
            ->get(route('insurance-deposits.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InsuranceDeposits/Show')
                ->where('deposit.notes_count', 1)
                ->where('deposit.notes.0.body', 'الصور مكتملة والتركيب سليم')
                ->where('deposit.notes.0.user_name', 'مدير العمال')
                ->where('deposit.notes.0.user_role', 'مدير العمال')
            );
    }

    public function test_workers_cannot_add_insurance_notes(): void
    {
        $order = $this->makeEligibleOrder();
        $worker = User::factory()->staff(User::ROLE_WORKER)->create();

        $this->actingAs($worker)
            ->post(route('insurance-deposits.notes.store', $order), [
                'body' => 'ملاحظة من عامل',
            ])
            ->assertRedirect(route('pwa.dashboard'));

        $this->assertDatabaseMissing('worker_order_notes', [
            'order_id' => $order->id,
            'body' => 'ملاحظة من عامل',
        ]);
    }

    private function makeOpenOrder(User $staff): Order
    {
        return Order::query()->create([
            'user_id' => $staff->id,
            'customer_name' => 'عميل جديد',
            'customer_phone' => '0501111111',
            'order_number' => Order::generateOrderNumber(),
            'total_amount' => 800,
            'amount_paid' => 800,
            'currency' => 'SAR',
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => 'bank_transfer',
        ]);
    }

    private function makeEligibleOrder(): Order
    {
        $owner = User::factory()->admin()->create();

        return Order::query()->create([
            'user_id' => $owner->id,
            'customer_name' => 'عميل التأمين',
            'customer_phone' => '0500000000',
            'order_number' => Order::generateOrderNumber(),
            'total_amount' => 1000,
            'amount_paid' => 1000,
            'currency' => 'SAR',
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_method' => 'bank_transfer',
            'insurance_amount' => 200,
            'insurance_status' => 'pending',
            'work_order_approved_at' => now()->subDay(),
            'warehouse_returned_at' => now()->subHour(),
            'insurance_refund_requested_at' => now()->subHour(),
        ]);
    }
}
