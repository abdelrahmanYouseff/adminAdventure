<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WorkerOrder;
use App\Support\OrderWhatsAppMessage;
use Inertia\Inertia;
use Inertia\Response;

class WorkerDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();

        $assignedOrdersQuery = Order::query()
            ->assignedToWorker($user)
            ->whereHas('workerOrders')
            ->with([
                'workerOrders' => fn ($q) => $q->orderBy('line_index'),
            ])
            ->withCount([
                'workerOrders as total_lines',
                'workerOrders as pending_lines' => fn ($q) => $q->where('status', 'pending'),
                'workerOrders as completed_lines' => fn ($q) => $q->where('status', 'completed'),
                'workerOrders as pending_pickup_lines' => fn ($q) => $q
                    ->where('status', 'completed')
                    ->whereNull('pickup_photo'),
            ])
            ->orderByRaw('activity_date IS NULL')
            ->orderBy('activity_date')
            ->orderByDesc('created_at');

        $installations = (clone $assignedOrdersQuery)
            ->where(function ($query) {
                $query->whereHas('workerOrders', fn ($q) => $q->where('status', 'pending'))
                    ->orWhereHas('workerOrders', fn ($q) => $q
                        ->where('status', 'completed')
                        ->whereNull('pickup_photo'));
            })
            ->get()
            ->map(fn (Order $order) => $this->formatInstallation($order))
            ->values()
            ->all();

        $pendingCount = count($installations);

        return Inertia::render('Dashboard', [
            'worker' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'pendingOrdersCount' => $pendingCount,
            'installations' => $installations,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInstallation(Order $order): array
    {
        $firstLine = $order->workerOrders->first();
        $pendingLines = (int) ($order->pending_lines ?? 0);
        $pendingPickup = (int) ($order->pending_pickup_lines ?? 0);
        $totalLines = (int) ($order->total_lines ?? $order->workerOrders->count());
        $address = $firstLine?->customer_address ?? $order->address;

        $phase = $pendingLines > 0 ? 'installation' : 'pickup';

        return [
            'id' => $order->id,
            'customer_name' => $firstLine?->customer_name ?? $order->customer_name,
            'map_url' => $this->resolveMapUrl($address),
            'customer_phone' => $order->customer_phone,
            'installation_date' => ($firstLine?->installation_date ?? $order->activity_date)?->format('Y-m-d'),
            'status' => $pendingLines > 0 ? 'pending' : 'completed',
            'phase' => $phase,
            'products_count' => $totalLines,
            'pending_count' => $pendingLines,
            'pending_pickup_count' => $pendingPickup,
            'completed_count' => (int) ($order->completed_lines ?? 0),
            'preview_products' => $order->workerOrders
                ->take(3)
                ->map(fn (WorkerOrder $line) => $line->product_name)
                ->values()
                ->all(),
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
