<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppInbound;
use App\Models\InboxWebhookLog;
use App\Services\Inbox\WhatsAppGraphClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppGraphClient $graph): SymfonyResponse
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        $expected = $graph->verifyToken();

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            InboxWebhookLog::query()->create([
                'event' => 'verify',
                'outcome' => 'ok',
                'http_status' => 200,
            ]);

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        InboxWebhookLog::query()->create([
            'event' => 'verify',
            'outcome' => 'rejected',
            'reason' => 'token_mismatch',
            'http_status' => 403,
        ]);

        return response('Forbidden', 403);
    }

    public function receive(Request $request, WhatsAppGraphClient $graph): Response
    {
        $raw = $request->getContent();
        $secret = $graph->appSecret();
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if ($secret !== '') {
            $expected = 'sha256='.hash_hmac('sha256', $raw, $secret);

            if ($signature === '' || ! hash_equals($expected, $signature)) {
                InboxWebhookLog::query()->create([
                    'event' => 'post',
                    'outcome' => 'rejected',
                    'reason' => 'bad_signature',
                    'http_status' => 403,
                ]);

                return response()->noContent(403);
            }
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            $payload = $request->all();
        }

        $phoneNumberId = (string) data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id', '');

        if ($phoneNumberId !== '' && ! $graph->belongsToThisSystem($phoneNumberId)) {
            InboxWebhookLog::query()->create([
                'event' => 'post',
                'outcome' => 'ignored',
                'reason' => 'foreign_phone_number_id',
                'http_status' => 204,
                'phone_number_id' => $phoneNumberId,
            ]);

            return response()->noContent();
        }

        $job = new ProcessWhatsAppInbound(is_array($payload) ? $payload : [], $phoneNumberId ?: null);

        if (config('services.whatsapp.dispatch_sync')) {
            dispatch_sync($job);
        } else {
            dispatch($job);
        }

        return response()->noContent();
    }
}
