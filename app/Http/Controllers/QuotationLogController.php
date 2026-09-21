<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Models\QuotationLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuotationLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('search')->toString());
        $filter = $request->string('filter')->toString() ?: 'all';

        if (! in_array($filter, ['all', 'edited'], true)) {
            $filter = 'all';
        }

        $query = Quotation::query()
            ->with(['quotationLogs' => fn ($builder) => $builder->orderBy('id')])
            ->latest('id');

        if ($filter === 'edited') {
            $query->whereHas('quotationLogs', fn ($builder) => $builder->where('action', QuotationLog::ACTION_UPDATED));
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('quotation_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhereHas('quotationLogs', function ($logs) use ($search) {
                        $logs->where('user_name', 'like', "%{$search}%");
                    });
            });
        }

        $quotations = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Quotation $quotation) => $this->presentQuotation($quotation));

        return Inertia::render('QuotationLogs/Index', [
            'quotations' => $quotations,
            'filters' => [
                'search' => $search,
                'filter' => $filter,
            ],
            'stats' => [
                'all' => Quotation::query()->count(),
                'edited' => Quotation::query()
                    ->whereHas('quotationLogs', fn ($builder) => $builder->where('action', QuotationLog::ACTION_UPDATED))
                    ->count(),
                'events' => QuotationLog::query()->count(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentQuotation(Quotation $quotation): array
    {
        $created = $quotation->quotationLogs->firstWhere('action', QuotationLog::ACTION_CREATED);
        $updates = $quotation->quotationLogs
            ->where('action', QuotationLog::ACTION_UPDATED)
            ->values();
        $lastUpdate = $updates->last();

        return [
            'id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'customer_name' => $quotation->customer_name,
            'created_by' => $created ? $this->presentActor($created) : $this->unknownActor(),
            'created_at' => $created?->created_at?->toIso8601String() ?? $quotation->created_at?->toIso8601String(),
            'updated_by' => $lastUpdate ? $this->presentActor($lastUpdate) : null,
            'updated_at' => $lastUpdate?->created_at?->toIso8601String(),
            'updates' => $updates->map(fn (QuotationLog $log) => [
                'id' => $log->id,
                'user' => $this->presentActor($log),
                'created_at' => $log->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return array{id: int|null, name: string, role: string|null, role_label: string|null}
     */
    private function presentActor(QuotationLog $log): array
    {
        $name = $log->user_name
            ?: $log->user?->customer_name
            ?: $log->user?->email;

        if (! $name) {
            $name = $log->action === QuotationLog::ACTION_CREATED ? 'النظام' : 'غير معروف';
        }

        $role = $log->user_role ?: $log->user?->role;

        return [
            'id' => $log->user_id,
            'name' => $name,
            'role' => $role,
            'role_label' => $this->roleLabel($role),
        ];
    }

    /**
     * @return array{id: null, name: string, role: null, role_label: null}
     */
    private function unknownActor(): array
    {
        return [
            'id' => null,
            'name' => 'غير مسجّل',
            'role' => null,
            'role_label' => null,
        ];
    }

    private function roleLabel(?string $role): ?string
    {
        if (! $role) {
            return null;
        }

        return match ($role) {
            'admin' => 'ادمن',
            'general_manager' => 'مدير عام',
            'manager' => 'مسئول',
            'accounts' => 'حسابات',
            'workers_manager' => 'مدير العمال',
            'worker' => 'عامل',
            'warehouse_keeper' => 'أمين مستودع',
            default => 'عميل',
        };
    }
}
