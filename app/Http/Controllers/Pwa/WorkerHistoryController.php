<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WorkerOrder;
use App\Support\OrderWhatsAppMessage;
use Inertia\Inertia;
use Inertia\Response;

class WorkerHistoryController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();

        $currentCount = Order::query()
            ->assignedToWorker($user)
            ->whereHas('workerOrders', fn ($q) => $q->where('status', 'pending'))
            ->count();

        $history = Order::query()
            ->assignedToWorker($user)
            ->whereHas('workerOrders')
            ->whereDoesntHave('workerOrders', fn ($q) => $q->where('status', 'pending'))
            ->with([
                'workerOrders' => fn ($q) => $q->orderBy('line_index'),
            ])
            ->withCount([
                'workerOrders as total_lines',
                'workerOrders as completed_lines' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get()
            ->sortByDesc(function (Order $order) {
                return $order->workerOrders
                    ->map(fn (WorkerOrder $line) => $line->completed_at)
                    ->filter()
                    ->max();
            })
            ->values()
            ->map(fn (Order $order) => $this->formatHistoryItem($order))
            ->all();

        return Inertia::render('History', [
            'worker' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'currentCount' => $currentCount,
            'history' => $history,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatHistoryItem(Order $order): array
    {
        $firstLine = $order->workerOrders->first();
        $address = $firstLine?->customer_address ?? $order->address;

        $latestAt = $order->workerOrders
            ->map(fn (WorkerOrder $line) => $line->completed_at)
            ->filter()
            ->sortDesc()
            ->first();

        return [
            'id' => $order->id,
            'customer_name' => $firstLine?->customer_name ?? $order->customer_name,
            'map_url' => $this->resolveMapUrl($address),
            'installation_date' => ($order->scheduledInstallationDate() ?? $firstLine?->installation_date)?->format('Y-m-d'),
            'completed_at' => $latestAt?->toIso8601String(),
            'products_count' => (int) ($order->total_lines ?? $order->workerOrders->count()),
            'products' => $order->workerOrders->map(fn (WorkerOrder $line) => [
                'id' => $line->id,
                'product_name' => $line->product_name,
                'product_image_url' => $line->product_image_url,
                'installation_photo_url' => $line->installation_photo_url,
                'completed_at' => $line->completed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function resolveMapUrl(?string $address): ?string
    {
        if (! $address || trim($address) === '') {
            return null;
        }

        $trimmed = trim($address);

        if (preg_match('#^https?://#i', $trimmed)) {
            return $trimmed;
        }

        return OrderWhatsAppMessage::locationMapsUrl($trimmed);
    }
}
