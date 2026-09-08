<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_settings', function (Blueprint $table) {
            $table->id();
            $table->string('timezone')->default('Asia/Riyadh');
            $table->string('verify_token')->nullable();
            $table->text('app_secret')->nullable();
            $table->boolean('whatsapp_ai_enabled')->default(false);
            $table->text('whatsapp_ai_openai_key')->nullable();
            $table->string('whatsapp_ai_model')->default('gpt-4o-mini');
            $table->text('whatsapp_ai_system_prompt')->nullable();
            $table->text('whatsapp_ai_purpose')->nullable();
            $table->text('whatsapp_ai_tone')->nullable();
            $table->text('whatsapp_ai_handoff_rules')->nullable();
            $table->unsignedInteger('whatsapp_ai_max_reply_chars')->default(800);
            $table->unsignedInteger('whatsapp_ai_bot_pause_minutes')->default(720);
            $table->boolean('qa_rating_enabled')->default(false);
            $table->unsignedInteger('qa_rating_delay_amount')->default(1);
            $table->string('qa_rating_delay_unit', 16)->default('days');
            $table->string('qa_rating_template_name')->nullable();
            $table->string('qa_rating_template_language', 16)->default('ar');
            $table->text('qa_rating_notify_emails')->nullable();
            $table->timestamps();
        });

        Schema::create('inbox_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number', 32);
            $table->string('name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->boolean('subscribed')->default(true);
            $table->timestamps();

            $table->unique('phone_number');
        });

        Schema::create('inbox_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('inbox_contacts')->cascadeOnDelete();
            $table->string('status', 16)->default('open');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('bot_paused_until')->nullable();
            $table->boolean('needs_human_agent')->default(false);
            $table->string('handoff_reason')->nullable();
            $table->text('ai_lead_requirements')->nullable();
            $table->string('last_message_preview')->nullable();
            $table->string('last_message_direction', 16)->nullable();
            $table->string('last_sender_type', 16)->nullable();
            $table->string('last_message_type', 32)->nullable();
            $table->timestamps();

            $table->unique('contact_id');
            $table->index(['status', 'last_message_at', 'id']);
            $table->index(['assigned_user_id', 'status']);
            $table->index('needs_human_agent');
        });

        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('inbox_conversations')->cascadeOnDelete();
            $table->string('sender_type', 16);
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('direction', 16);
            $table->string('message_type', 32);
            $table->json('payload')->nullable();
            $table->string('external_message_id')->nullable();
            $table->string('status', 16)->default('queued');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique('external_message_id');
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('inbox_quick_replies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('inbox_media_uploads', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('disk')->default('s3');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('original_name')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('inbox_service_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('inbox_conversations')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('status', 32)->default('invite');
            $table->string('step', 32)->default('awaiting_score');
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('comment')->nullable();
            $table->string('media_uuid')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'status']);
        });

        Schema::create('inbox_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event', 64);
            $table->string('outcome', 32);
            $table->string('reason')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('phone_number_id')->nullable();
            $table->string('external_message_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index('phone_number_id');
        });

        \Illuminate\Support\Facades\DB::table('inbox_quick_replies')->insert([
            [
                'title' => 'ترحيب',
                'body' => 'مرحباً بك في عالم المغامرة للترفيه، كيف نقدر نساعدك؟',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'متابعة طلب',
                'body' => 'تم استلام طلبكم وسنعاود التواصل قريباً.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Welcome',
                'body' => 'Welcome to Adventure World. How can we help you today?',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('inbox_webhook_logs');
        Schema::dropIfExists('inbox_service_ratings');
        Schema::dropIfExists('inbox_media_uploads');
        Schema::dropIfExists('inbox_quick_replies');
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('inbox_conversations');
        Schema::dropIfExists('inbox_contacts');
        Schema::dropIfExists('inbox_settings');
    }
};
