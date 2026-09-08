<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\InboxSetting;
use App\Services\Inbox\WhatsAppGraphClient;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WhatsappAiSettingsController extends Controller
{
    public function edit(WhatsAppGraphClient $graph)
    {
        $settings = InboxSetting::current();
        $quality = $graph->isConfigured() ? $graph->phoneQuality() : ['success' => false];

        return Inertia::render('settings/WhatsappAi', [
            'settings' => [
                'whatsapp_ai_enabled' => $settings->whatsapp_ai_enabled,
                'has_openai_key' => $settings->hasOpenaiKey(),
                'whatsapp_ai_model' => $settings->whatsapp_ai_model,
                'whatsapp_ai_system_prompt' => $settings->whatsapp_ai_system_prompt,
                'whatsapp_ai_purpose' => $settings->whatsapp_ai_purpose,
                'whatsapp_ai_tone' => $settings->whatsapp_ai_tone,
                'whatsapp_ai_handoff_rules' => $settings->whatsapp_ai_handoff_rules,
                'whatsapp_ai_max_reply_chars' => $settings->whatsapp_ai_max_reply_chars,
                'whatsapp_ai_bot_pause_minutes' => $settings->whatsapp_ai_bot_pause_minutes,
                'timezone' => $settings->timezone,
                'has_app_secret' => $settings->hasAppSecret(),
                'verify_token' => $settings->resolvedVerifyToken(),
                'waba_id' => $settings->resolvedWabaId(),
                'waba_id_locked' => filled(config('services.whatsapp.waba_id')),
            ],
            'channel' => [
                'phone_number_id' => $graph->phoneNumberId(),
                'graph_version' => $graph->graphVersion(),
                'webhook_url' => rtrim((string) config('app.public_url', config('app.url')), '/').'/webhooks/whatsapp',
                'quality' => $quality,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'whatsapp_ai_enabled' => ['required', 'boolean'],
            'whatsapp_ai_openai_key' => ['nullable', 'string', 'max:500'],
            'whatsapp_ai_model' => ['required', 'string', 'max:80'],
            'whatsapp_ai_system_prompt' => ['nullable', 'string', 'max:8000'],
            'whatsapp_ai_purpose' => ['nullable', 'string', 'max:2000'],
            'whatsapp_ai_tone' => ['nullable', 'string', 'max:2000'],
            'whatsapp_ai_handoff_rules' => ['nullable', 'string', 'max:4000'],
            'whatsapp_ai_max_reply_chars' => ['required', 'integer', 'min:80', 'max:2000'],
            'whatsapp_ai_bot_pause_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
            'timezone' => ['required', 'string', 'max:64'],
            'app_secret' => ['nullable', 'string', 'max:255'],
            'verify_token' => ['nullable', 'string', 'max:120'],
            'waba_id' => ['nullable', 'string', 'max:64'],
        ]);

        $settings = InboxSetting::current();
        $settings->fill([
            'whatsapp_ai_enabled' => $data['whatsapp_ai_enabled'],
            'whatsapp_ai_model' => $data['whatsapp_ai_model'],
            'whatsapp_ai_system_prompt' => $data['whatsapp_ai_system_prompt'],
            'whatsapp_ai_purpose' => $data['whatsapp_ai_purpose'],
            'whatsapp_ai_tone' => $data['whatsapp_ai_tone'],
            'whatsapp_ai_handoff_rules' => $data['whatsapp_ai_handoff_rules'],
            'whatsapp_ai_max_reply_chars' => $data['whatsapp_ai_max_reply_chars'],
            'whatsapp_ai_bot_pause_minutes' => $data['whatsapp_ai_bot_pause_minutes'],
            'timezone' => $data['timezone'],
        ]);

        if (filled($data['whatsapp_ai_openai_key'] ?? null)) {
            $settings->setEncryptedOpenaiKey($data['whatsapp_ai_openai_key']);
        }

        if (filled($data['app_secret'] ?? null)) {
            $settings->setEncryptedAppSecret($data['app_secret']);
        }

        if (filled($data['verify_token'] ?? null) && ! filled(config('services.whatsapp.verify_token'))) {
            $settings->verify_token = $data['verify_token'];
        }

        if (! filled(config('services.whatsapp.waba_id'))) {
            $settings->waba_id = filled($data['waba_id'] ?? null) ? trim((string) $data['waba_id']) : null;
        }

        $settings->save();

        return back()->with('success', 'تم حفظ إعدادات بوت واتساب.');
    }
}
