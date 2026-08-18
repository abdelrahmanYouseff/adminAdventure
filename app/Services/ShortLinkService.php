<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShortLink;
use App\Support\PublicAppUrl;
use Illuminate\Support\Str;

class ShortLinkService
{
    public function createDeliveryNoteLink(Order $order): ShortLink
    {
        $order->loadMissing('invoice:id,invoice_number');
        $targetKey = $order->invoice?->invoice_number ?? $order->order_number;

        $existing = ShortLink::query()
            ->where('type', ShortLink::TYPE_DELIVERY_NOTE)
            ->where('order_id', $order->id)
            ->latest('id')
            ->first();

        if ($existing) {
            $existing->fill([
                'target_key' => $targetKey,
                'expires_at' => null,
            ]);
            $existing->save();

            return $existing;
        }

        return ShortLink::query()->create([
            'code' => $this->uniqueCode(),
            'type' => ShortLink::TYPE_DELIVERY_NOTE,
            'order_id' => $order->id,
            'target_key' => $targetKey,
            'expires_at' => null,
            'hits' => 0,
        ]);
    }

    public function publicUrl(ShortLink $link): string
    {
        return rtrim(PublicAppUrl::base(), '/').'/d/'.$link->code;
    }

    /**
     * Value passed to Meta URL button {{1}}.
     *
     * Template website URL must be the admin host, not the Salla shop:
     * https://admin.adventureksa.com/d/{{1}}
     */
    public function whatsappButtonSuffix(ShortLink $link): string
    {
        $mode = (string) config('services.whatsapp.delivery_note_button_mode', 'code');

        if ($mode === 'system') {
            $key = (string) ($link->target_key ?: '');

            if ($key !== '') {
                return 'worker-orders/'.$key.'/delivery-note';
            }
        }

        if ($mode === 'path') {
            return 'd/'.$link->code;
        }

        return $link->code;
    }

    private function uniqueCode(int $length = 8): string
    {
        do {
            $code = Str::lower(Str::random($length));
        } while (ShortLink::query()->where('code', $code)->exists());

        return $code;
    }
}
