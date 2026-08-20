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
        return rtrim(PublicAppUrl::base(), '/').'/dn/'.$this->whatsappButtonSuffix($link);
    }

    /**
     * Value passed to Meta URL button {{1}}.
     *
     * Website URL in Meta is:
     * https://www.admin.adventureksa.com/dn/{{1}}
     * so this must be the delivery-note id only (e.g. S-202608142), not a full URL.
     */
    public function whatsappButtonSuffix(ShortLink $link): string
    {
        $id = trim((string) ($link->target_key ?: ''));

        return $id !== '' ? $id : $link->code;
    }

    private function uniqueCode(int $length = 8): string
    {
        do {
            $code = Str::lower(Str::random($length));
        } while (ShortLink::query()->where('code', $code)->exists());

        return $code;
    }
}
