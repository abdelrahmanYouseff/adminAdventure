<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderAssembler;
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
                'workerAssemblers' => fn ($q) => $q->where(function ($inner) use ($user) {
                    $inner->where('user_id', $user->id);
                    if ($user->name !== '') {
                        $inner->orWhere('worker_name', $user->name);
                    }
                }),
            ])
            ->withCount([
                'workerOrders as total_lines',
                'workerOrders as pending_lines' => fn ($q) => $q->where('status', 'pending'),
                'workerOrders as completed_lines' => fn ($q) => $q->where('status', 'completed'),
                'workerOrders as pending_pickup_lines' => fn ($q) => $q->whereNull('pickup_photo'),
                'workerOrders as completed_pickup_lines' => fn ($q) => $q->whereNotNull('pickup_photo'),
            ])
            ->orderByRaw('activity_date IS NULL')
            ->orderBy('activity_date')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Order $order) => $this->formatInstallation($order, $user))
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
    private function formatInstallation(Order $order, $user): array
    {
        $firstLine = $order->workerOrders->first();
        $assignmentType = $order->primaryWorkerAssignmentType($user);
        $isDismantling = $order->workerIsInDismantlingPhase($user);
        $address = $firstLine?->customer_address ?? $order->address;
        $isApproved = (bool) $order->work_order_approved_at;
        $photosReady = $order->hasAllWorkerPhotos();

        if ($isDismantling) {
            $pendingLines = (int) ($order->pending_pickup_lines ?? $order->workerOrders->whereNull('pickup_photo')->count());
            $totalLines = (int) ($order->total_lines ?? $order->workerOrders->count());
            $completedLines = (int) ($order->completed_pickup_lines ?? ($totalLines - $pendingLines));

            if ($pendingLines > 0) {
                $listStatus = 'current';
                $phase = 'dismantling';
            } elseif ($order->warehouse_returned_at) {
                $listStatus = 'completed';
                $phase = 'done';
            } elseif ($order->warehouse_rejected_at) {
                $listStatus = 'awaiting_approval';
                $phase = 'awaiting';
            } else {
                $listStatus = 'awaiting_approval';
                $phase = 'awaiting';
            }

            $scheduledDate = $order->dismantling_at?->format('Y-m-d');
            $scheduledTime = $order->dismantling_at?->format('H:i');
        } else {
            $pendingLines = (int) ($order->pending_lines ?? 0);
            $totalLines = (int) ($order->total_lines ?? $order->workerOrders->count());
            $completedLines = (int) ($order->completed_lines ?? 0);

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
            $scheduledDate = ($firstLine?->installation_date ?? $order->activity_date)?->format('Y-m-d');
            $scheduledTime = $rawTime ? Carbon::parse($rawTime)->format('H:i') : null;
        }

        return [
            'id' => $order->id,
            'customer_name' => $firstLine?->customer_name ?? $order->customer_name,
            'map_url' => $this->resolveMapUrl($address),
            'customer_phone' => $order->customer_phone,
            'installation_date' => $scheduledDate,
            'activity_time' => $scheduledTime,
            'status' => $pendingLines > 0 ? 'pending' : 'completed',
            'list_status' => $listStatus,
            'phase' => $phase,
            'task_type' => $isDismantling
                ? 'dismantling'
                : ($assignmentType === 'both' ? 'both' : 'installation'),
            'task_label' => $isDismantling
                ? 'فك'
                : ($assignmentType === 'both' ? 'تركيب + فك' : 'تركيب'),
            'is_approved' => $isApproved,
            'products_count' => $totalLines,
            'pending_count' => $pendingLines,
            'completed_count' => $completedLines,
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
