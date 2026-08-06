<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WorkerOrder;
use App\Support\OrderWhatsAppMessage;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class WorkerDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();

        $installations = Order::query()
            ->assignedToWorker($user)
            ->whereHas('workerOrders')
            ->with([
                'workerOrders' => fn ($q) => $q->orderBy('line_index'),
            ])
            ->withCount([
                'workerOrders as total_lines',
                'workerOrders as pending_lines' => fn ($q) => $q->where('status', 'pending'),
                'workerOrders as completed_lines' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->orderByRaw('activity_date IS NULL')
            ->orderBy('activity_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Order $order) => $this->formatInstallation($order))
            ->values()
            ->all();

        $currentCount = collect($installations)->where('list_status', 'current')->count();
        $awaitingCount = collect($installations)->where('list_status', 'awaiting_approval')->count();
        $completedCount = collect($installations)->where('list_status', 'completed')->count();

        return Inertia::render('Dashboard', [
            'worker' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'pendingOrdersCount' => $currentCount,
            'counts' => [
                'current' => $currentCount,
                'awaiting_approval' => $awaitingCount,
                'completed' => $completedCount,
                'all' => count($installations),
            ],
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
        $totalLines = (int) ($order->total_lines ?? $order->workerOrders->count());
        $address = $firstLine?->customer_address ?? $order->address;
        $isApproved = (bool) $order->work_order_approved_at;
        $photosReady = $order->hasAllWorkerPhotos();

        if ($pendingLines > 0) {
            $listStatus = 'current';
            $phase = 'installation';
        } elseif ($photosReady && ! $isApproved) {
            $listStatus = 'awaiting_approval';
            $phase = 'awaiting';
        } else {
            $listStatus = 'completed';
            $phase = 'done';
        }

        $rawTime = $order->getAttributes()['activity_time'] ?? null;

        return [
            'id' => $order->id,
            'customer_name' => $firstLine?->customer_name ?? $order->customer_name,
            'map_url' => $this->resolveMapUrl($address),
            'customer_phone' => $order->customer_phone,
            'installation_date' => ($firstLine?->installation_date ?? $order->activity_date)?->format('Y-m-d'),
            'activity_time' => $rawTime ? Carbon::parse($rawTime)->format('H:i') : null,
            'status' => $pendingLines > 0 ? 'pending' : 'completed',
            'list_status' => $listStatus,
            'phase' => $phase,
            'is_approved' => $isApproved,
            'products_count' => $totalLines,
            'pending_count' => $pendingLines,
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
