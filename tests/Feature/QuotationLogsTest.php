<?php

namespace Tests\Feature;

use App\Models\Quotation;
use App\Models\QuotationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuotationLogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guests_are_redirected_from_quotation_logs(): void
    {
        $this->get(route('quotation-logs.index'))
            ->assertRedirect(route('login'));
    }

    public function test_accounts_role_cannot_open_quotation_logs(): void
    {
        $accounts = User::factory()->staff(User::ROLE_ACCOUNTS)->create();

        $this->actingAs($accounts)
            ->get(route('quotation-logs.index'))
            ->assertRedirect(route('quotations.index'));
    }

    public function test_manager_cannot_open_quotation_logs(): void
    {
        $manager = User::factory()->staff(User::ROLE_MANAGER)->create();

        $this->actingAs($manager)
            ->get(route('quotation-logs.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_existing_quotations_appear_even_without_historical_logs(): void
    {
        $admin = User::factory()->admin()->create();
        $quotation = Quotation::withoutEvents(fn () => $this->makeQuotation($admin, 'لمي الصغير'));

        $this->actingAs($admin)
            ->get(route('quotation-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('QuotationLogs/Index')
                ->has('quotations.data', 1)
                ->where('quotations.data.0.quotation_number', $quotation->quotation_number)
                ->where('quotations.data.0.customer_name', 'لمي الصغير')
                ->where('quotations.data.0.created_by.name', 'غير مسجّل')
                ->where('quotations.data.0.updated_by', null)
                ->where('stats.all', 1)
                ->where('stats.edited', 0)
            );
    }

    public function test_creating_a_quotation_records_the_authenticated_user(): void
    {
        $admin = User::factory()->admin()->create([
            'customer_name' => 'أحمد المنشئ',
        ]);

        $this->actingAs($admin);
        $quotation = $this->makeQuotation($admin, 'عميل تجريبي');

        $this->assertDatabaseHas('quotation_logs', [
            'quotation_id' => $quotation->id,
            'action' => QuotationLog::ACTION_CREATED,
            'user_id' => $admin->id,
            'user_name' => 'أحمد المنشئ',
        ]);
    }

    public function test_updating_a_quotation_records_who_and_when(): void
    {
        $admin = User::factory()->admin()->create([
            'customer_name' => 'سارة المعدّلة',
        ]);

        $this->actingAs($admin);
        $quotation = $this->makeQuotation($admin, 'عميل تجريبي');

        $this->actingAs($admin);
        $quotation->update(['customer_name' => 'عميل بعد التعديل']);

        $this->assertDatabaseHas('quotation_logs', [
            'quotation_id' => $quotation->id,
            'action' => QuotationLog::ACTION_UPDATED,
            'user_id' => $admin->id,
            'user_name' => 'سارة المعدّلة',
        ]);
    }

    public function test_payment_token_updates_are_not_logged_as_edits(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $quotation = $this->makeQuotation($admin, 'عميل تجريبي');

        $quotation->update(['payment_token' => 'tokentoken12ab']);

        $this->assertSame(1, QuotationLog::query()->where('quotation_id', $quotation->id)->count());
        $this->assertDatabaseMissing('quotation_logs', [
            'quotation_id' => $quotation->id,
            'action' => QuotationLog::ACTION_UPDATED,
        ]);
    }

    public function test_unauthenticated_updates_are_not_logged(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $quotation = $this->makeQuotation($admin, 'عميل تجريبي');
        auth()->logout();

        $quotation->update(['notes' => 'تحديث بدون مستخدم']);

        $this->assertDatabaseMissing('quotation_logs', [
            'quotation_id' => $quotation->id,
            'action' => QuotationLog::ACTION_UPDATED,
        ]);
    }

    public function test_admin_can_see_creator_and_editor_on_quotation_logs_page(): void
    {
        $admin = User::factory()->admin()->create([
            'customer_name' => 'مدير النظام',
        ]);

        $this->actingAs($admin);
        $quotation = $this->makeQuotation($admin, 'لمي الصغير');
        $quotation->update(['customer_address' => 'الرياض']);

        $this->actingAs($admin)
            ->get(route('quotation-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('QuotationLogs/Index')
                ->has('quotations.data', 1)
                ->where('quotations.data.0.quotation_number', $quotation->quotation_number)
                ->where('quotations.data.0.customer_name', 'لمي الصغير')
                ->where('quotations.data.0.created_by.name', 'مدير النظام')
                ->where('quotations.data.0.updated_by.name', 'مدير النظام')
                ->where('stats.all', 1)
                ->where('stats.edited', 1)
            );
    }

    private function makeQuotation(User $owner, string $customerName): Quotation
    {
        return Quotation::query()->create([
            'user_id' => $owner->id,
            'quotation_number' => Quotation::generateQuotationNumber(),
            'customer_name' => $customerName,
            'valid_until' => now()->addDays(7)->toDateString(),
            'total_amount' => 500,
            'status' => 'draft',
        ]);
    }
}
