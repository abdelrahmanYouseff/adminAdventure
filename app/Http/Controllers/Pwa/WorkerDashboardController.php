<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Models\WorkerOrderAssembler;
use App\Support\OrderWhatsAppMessage;
use Inertia\Inertia;
use Inertia\Response;

class WorkerDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();

        $orders = Order::query()
            ->assignedToWorker($user)
            ->whereHas('workerOrders')
            ->where(function ($query) {
                // Keep lists light on mobile: active work + recent completed only.
                $query->whereHas('workerOrders', fn ($q) => $q->where('status', 'pending'))
                    ->orWhereNull('work_order_approved_at')
                    ->orWhereNull('warehouse_returned_at')
                    ->orWhere('updated_at', '>=', now()->subDays(45));
            })
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
            ->limit(60)
            ->get();

        $installations = $orders
            ->flatMap(fn (Order $order) => $this->formatTaskCards($order, $user))
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
     * One card per assigned task (installation and/or dismantling), never merged.
     *
     * @return list<array<string, mixed>>
     */
    private function formatTaskCards(Order $order, User $user): array
    {
        $hasInstallation = $order->isAssignedToWorker($user, WorkerOrderAssembler::TYPE_INSTALLATION);
        $hasDismantling = $order->isAssignedToWorker($user, WorkerOrderAssembler::TYPE_DISMANTLING);

        $cards = [];

        if ($hasInstallation) {
            $cards[] = $this->formatTaskCard($order, $user, WorkerOrderAssembler::TYPE_INSTALLATION);
        }

        if ($hasDismantling && $order->canEnterReturnsFlow()) {
            $cards[] = $this->formatTaskCard($order, $user, WorkerOrderAssembler::TYPE_DISMANTLING);
        }

        return $cards;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTaskCard(Order $order, User $user, string $taskType): array
    {
        $firstLine = $order->workerOrders->first();
        $address = $firstLine?->customer_address ?? $order->address;
        $isDismantling = $taskType === WorkerOrderAssembler::TYPE_DISMANTLING;
        $totalLines = (int) ($order->total_lines ?? $order->workerOrders->count());

        if ($isDismantling) {
            $pendingLines = (int) ($order->pending_pickup_lines ?? $order->workerOrders->whereNull('pickup_photo')->count());
            $completedLines = (int) ($order->completed_pickup_lines ?? ($totalLines - $pendingLines));

            if ($pendingLines > 0) {
                $listStatus = 'current';
                $phase = 'dismantling';
            } elseif ($order->warehouse_returned_at) {
                $listStatus = 'completed';
                $phase = 'done';
            } else {
                $listStatus = 'awaiting_approval';
                $phase = 'awaiting';
            }

            $scheduledDate = $order->dismantling_at?->format('Y-m-d');
            $scheduledTime = $order->dismantling_at?->format('H:i');
            $isApproved = filled($order->warehouse_returned_at);
        } else {
            $pendingLines = (int) ($order->pending_lines ?? 0);
            $completedLines = (int) ($order->completed_lines ?? 0);
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

            $scheduledDate = ($order->scheduledInstallationDate() ?? $firstLine?->installation_date)?->format('Y-m-d');
            $scheduledTime = $order->scheduledInstallationTime();
        }

        return [
            'id' => $order->id,
            'list_key' => $order->id.'-'.$taskType,
            'customer_name' => $firstLine?->customer_name ?? $order->customer_name,
            'map_url' => $this->resolveMapUrl($address),
            'customer_phone' => $order->customer_phone,
            'installation_date' => $scheduledDate,
            'activity_time' => $scheduledTime,
            'status' => $pendingLines > 0 ? 'pending' : 'completed',
            'list_status' => $listStatus,
            'phase' => $phase,
            'task_type' => $isDismantling ? 'dismantling' : 'installation',
            'task_label' => $isDismantling ? 'فك' : 'تركيب',
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
