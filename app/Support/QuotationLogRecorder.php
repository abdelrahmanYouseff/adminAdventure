<?php

namespace App\Support;

use App\Models\Quotation;
use App\Models\QuotationLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class QuotationLogRecorder
{
    /**
     * Automatic fields that should not count as a staff edit.
     *
     * @var list<string>
     */
    private const IGNORED_ATTRIBUTES = [
        'updated_at',
        'payment_token',
    ];

    public static function created(Quotation $quotation): QuotationLog
    {
        return self::record($quotation, QuotationLog::ACTION_CREATED);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function updated(Quotation $quotation, array $changes = []): ?QuotationLog
    {
        if ($changes === []) {
            $changes = self::meaningfulChanges($quotation);
        }

        if ($changes === []) {
            return null;
        }

        if (! Auth::check()) {
            return null;
        }

        return self::record($quotation, QuotationLog::ACTION_UPDATED, $changes);
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function meaningfulChanges(Quotation $quotation): array
    {
        $changes = [];

        foreach ($quotation->getChanges() as $key => $value) {
            if (in_array($key, self::IGNORED_ATTRIBUTES, true)) {
                continue;
            }

            $changes[$key] = [
                'from' => $quotation->getOriginal($key),
                'to' => $value,
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private static function record(Quotation $quotation, string $action, array $changes = []): QuotationLog
    {
        $user = Auth::user();
        $actor = $user instanceof User ? $user : null;

        return QuotationLog::query()->create([
            'quotation_id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'user_id' => $actor?->id,
            'user_name' => $actor?->customer_name ?: $actor?->email,
            'user_role' => $actor?->role,
            'action' => $action,
            'changes' => $changes !== [] ? $changes : null,
        ]);
    }
}
