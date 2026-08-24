<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $type = $request->string('type')->toString() ?: 'all';
        $status = $request->string('status')->toString() ?: 'all';

        $allowedTypes = ['all', 'installation_photos', 'dismantling_photos', 'work_order_issued'];
        $allowedStatuses = ['all', 'sent', 'failed', 'skipped'];

        if (! in_array($type, $allowedTypes, true)) {
            $type = 'all';
        }

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'all';
        }

        $query = EmailLog::query()
            ->with('order:id,order_number,customer_name')
            ->latest('id');

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('order_number', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%")
                    ->orWhere('recipients', 'like', "%{$search}%");
            });
        }

        $logs = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (EmailLog $log) => [
                'id' => $log->id,
                'type' => $log->type,
                'type_label' => $this->typeLabel($log->type),
                'status' => $log->status,
                'status_label' => $this->statusLabel($log->status),
                'subject' => $log->subject,
                'order_id' => $log->order_id,
                'order_number' => $log->order_number,
                'customer_name' => $log->order?->customer_name,
                'recipients' => $log->recipients ?? [],
                'recipients_count' => (int) $log->recipients_count,
                'error_message' => $log->error_message,
                'meta' => $log->meta ?? [],
                'sent_at' => $log->sent_at?->toIso8601String(),
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('EmailLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'status' => $status,
            ],
            'stats' => [
                'all' => EmailLog::query()->count(),
                'sent' => EmailLog::query()->where('status', 'sent')->count(),
                'failed' => EmailLog::query()->where('status', 'failed')->count(),
                'skipped' => EmailLog::query()->where('status', 'skipped')->count(),
            ],
        ]);
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'installation_photos' => 'صور التركيب',
            'dismantling_photos' => 'صور الفك',
            'work_order_issued' => 'إصدار أمر العمل',
            default => $type,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'sent' => 'تم الإرسال',
            'failed' => 'فشل',
            'skipped' => 'تم التجاهل',
            default => $status,
        };
    }
}
