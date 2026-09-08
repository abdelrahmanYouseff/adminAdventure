<?php

namespace Tests\Feature;

use App\Models\InboxContact;
use App\Models\InboxConversation;
use App\Models\InboxSetting;
use App\Models\User;
use App\Services\Inbox\InboundMessageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_verify_returns_challenge(): void
    {
        InboxSetting::query()->create(array_merge(InboxSetting::defaults(), [
            'verify_token' => 'advksa-wa-inbox-vtok-9f4c2e81',
        ]));

        config(['services.whatsapp.verify_token' => 'advksa-wa-inbox-vtok-9f4c2e81']);

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=advksa-wa-inbox-vtok-9f4c2e81&hub_challenge=CHALLENGE_OK')
            ->assertOk()
            ->assertSee('CHALLENGE_OK');
    }

    public function test_webhook_verify_rejects_foreign_token(): void
    {
        config(['services.whatsapp.verify_token' => 'advksa-wa-inbox-vtok-9f4c2e81']);

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=other-system-token&hub_challenge=NO')
            ->assertForbidden();
    }

    public function test_agents_can_open_inbox(): void
    {
        $user = User::factory()->admin()->create();

        $this->withoutVite()
            ->actingAs($user)
            ->get('/inbox')
            ->assertOk();
    }

    public function test_new_chat_creates_contact_and_opens_template_compose(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->post('/inbox', ['phone' => '+966538388299'])
            ->assertRedirect();

        $this->assertDatabaseHas('inbox_contacts', [
            'phone_number' => '+966538388299',
        ]);
    }

    public function test_inbound_from_this_phone_number_is_stored(): void
    {
        config(['services.whatsapp.phone_number_id' => '927497130457547']);

        $processor = app(InboundMessageProcessor::class);
        $processor->handle([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '927497130457547'],
                        'contacts' => [['profile' => ['name' => 'Test Customer']]],
                        'messages' => [[
                            'from' => '966500000001',
                            'id' => 'wamid.test-1',
                            'timestamp' => (string) time(),
                            'type' => 'text',
                            'text' => ['body' => 'Hello'],
                        ]],
                    ],
                ]],
            ]],
        ], '927497130457547');

        $this->assertDatabaseHas('inbox_messages', [
            'external_message_id' => 'wamid.test-1',
            'direction' => 'inbound',
        ]);
        $this->assertSame(1, InboxConversation::query()->count());
    }

    public function test_inbound_from_other_phone_number_is_ignored(): void
    {
        config(['services.whatsapp.phone_number_id' => '927497130457547']);

        app(InboundMessageProcessor::class)->handle([
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => '999999999999999'],
                        'messages' => [[
                            'from' => '966500000002',
                            'id' => 'wamid.other',
                            'type' => 'text',
                            'text' => ['body' => 'leak?'],
                        ]],
                    ],
                ]],
            ]],
        ], '999999999999999');

        $this->assertDatabaseCount('inbox_messages', 0);
        $this->assertDatabaseCount('inbox_contacts', 0);
    }

    public function test_resume_bot_clears_pause_without_needs_human(): void
    {
        $contact = InboxContact::query()->create([
            'phone_number' => '+966500000003',
            'name' => 'Agent Pause',
        ]);
        $conversation = InboxConversation::query()->create([
            'contact_id' => $contact->id,
            'status' => 'open',
            'needs_human_agent' => false,
            'bot_paused_until' => now()->addHours(12),
            'handoff_reason' => 'agent_reply',
        ]);

        $conversation->resumeBot();
        $conversation->refresh();

        $this->assertFalse($conversation->needs_human_agent);
        $this->assertNull($conversation->bot_paused_until);
    }

    public function test_closing_conversation_resumes_bot(): void
    {
        $user = User::factory()->admin()->create();
        $contact = InboxContact::query()->create(['phone_number' => '+966500000004']);
        $conversation = InboxConversation::query()->create([
            'contact_id' => $contact->id,
            'status' => 'open',
            'needs_human_agent' => true,
            'bot_paused_until' => now()->addYears(5),
            'handoff_reason' => 'manual',
        ]);

        $this->actingAs($user)
            ->patch('/inbox/'.$conversation->id.'/status', ['status' => 'closed'])
            ->assertRedirect();

        $conversation->refresh();
        $this->assertSame('closed', $conversation->status);
        $this->assertFalse($conversation->needs_human_agent);
        $this->assertNull($conversation->bot_paused_until);
    }

    public function test_unassign_only_self(): void
    {
        $me = User::factory()->admin()->create();
        $other = User::factory()->staff()->create();
        $contact = InboxContact::query()->create(['phone_number' => '+966500000005']);
        $conversation = InboxConversation::query()->create([
            'contact_id' => $contact->id,
            'assigned_user_id' => $other->id,
        ]);

        $this->actingAs($me)
            ->delete('/inbox/'.$conversation->id.'/assign')
            ->assertForbidden();
    }
}
