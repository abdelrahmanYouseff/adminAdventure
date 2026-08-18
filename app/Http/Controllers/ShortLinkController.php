<?php

namespace App\Http\Controllers;

use App\Models\ShortLink;
use App\Services\DeliveryNotePdfService;
use App\Services\ShortLinkService;
use App\Services\WorkerOrderSyncService;
use App\Support\DeliveryNotePdfData;
use Symfony\Component\HttpFoundation\Response;

class ShortLinkController extends Controller
{
    public function show(
        string $code,
        DeliveryNotePdfService $pdfService,
        WorkerOrderSyncService $syncService,
        ShortLinkService $shortLinks,
    ): Response {
        $link = ShortLink::query()->where('code', $code)->firstOrFail();

        $link->increment('hits');

        return match ($link->type) {
            ShortLink::TYPE_DELIVERY_NOTE => $this->deliveryNote($link, $pdfService, $syncService, $shortLinks),
            default => abort(404),
        };
    }

    private function deliveryNote(
        ShortLink $link,
        DeliveryNotePdfService $pdfService,
        WorkerOrderSyncService $syncService,
        ShortLinkService $shortLinks,
    ): Response {
        $order = $link->order;
        abort_unless($order, 404);

        if (! $order->workerOrders()->exists() && $order->hasApprovedPaymentReceipt()) {
            $syncService->syncFromOrder($order->fresh());
        }

        abort_unless($order->workerOrders()->exists(), 404);

        $data = DeliveryNotePdfData::fromOrder(
            $order->fresh(['workerOrders', 'invoice', 'products']),
            $shortLinks->publicUrl($link),
        );
        $pdf = $pdfService->render($data);
        $filename = 'delivery-note-'.$data->referenceNumber().'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
