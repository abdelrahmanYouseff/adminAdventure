<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\WhatsappNotificationRecipient;
use App\Services\WhatsAppCloudService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WhatsappNotificationSettingsController extends Controller
{
    public function index(): Response
    {
        $recipients = WhatsappNotificationRecipient::query()
            ->orderBy('id')
            ->get()
            ->map(fn (WhatsappNotificationRecipient $recipient) => [
                'id' => $recipient->id,
                'phone' => $recipient->phone,
                'display_phone' => $recipient->displayPhone(),
                'label' => $recipient->label,
                'is_active' => $recipient->is_active,
            ]);

        return Inertia::render('settings/WhatsAppNotifications', [
            'recipients' => $recipients,
            'whatsapp_configured' => app(WhatsAppCloudService::class)->isConfigured(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $normalized = WhatsAppCloudService::normalizePhone($data['phone']);

        if (! preg_match('/^9665\d{8}$/', $normalized)) {
            return back()->withErrors([
                'phone' => 'أدخل رقم جوال سعودي صحيحاً',
            ]);
        }

        if (WhatsappNotificationRecipient::query()->where('phone', $normalized)->exists()) {
            return back()->withErrors([
                'phone' => 'هذا الرقم مضاف مسبقاً',
            ]);
        }

        WhatsappNotificationRecipient::create([
            'phone' => $normalized,
            'label' => $data['label'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'تمت إضافة الرقم بنجاح');
    }

    public function update(Request $request, WhatsappNotificationRecipient $recipient): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'phone' => ['sometimes', 'string', 'max:20'],
        ]);

        if (isset($data['phone'])) {
            $normalized = WhatsAppCloudService::normalizePhone($data['phone']);

            if (! preg_match('/^9665\d{8}$/', $normalized)) {
                return back()->withErrors([
                    'phone' => 'أدخل رقم جوال سعودي صحيحاً',
                ]);
            }

            $exists = WhatsappNotificationRecipient::query()
                ->where('phone', $normalized)
                ->where('id', '!=', $recipient->id)
                ->exists();

            if ($exists) {
                return back()->withErrors([
                    'phone' => 'هذا الرقم مضاف مسبقاً',
                ]);
            }

            $recipient->phone = $normalized;
        }

        if (array_key_exists('label', $data)) {
            $recipient->label = $data['label'];
        }

        if (array_key_exists('is_active', $data)) {
            $recipient->is_active = (bool) $data['is_active'];
        }

        $recipient->save();

        return back()->with('success', 'تم تحديث الرقم بنجاح');
    }

    public function destroy(WhatsappNotificationRecipient $recipient): RedirectResponse
    {
        $recipient->delete();

        return back()->with('success', 'تم حذف الرقم');
    }
}
