<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderPaymentReceipt extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'order_id',
        'recorded_by',
        'receipt_number',
        'amount',
        'total_amount',
        'amount_paid_before',
        'amount_paid_after',
        'remaining_after',
        'payment_method',
        'type',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'rejected_at',
        'rejected_by',
        'notes',
        'proof_image',
        'account_number',
    ];

    protected $appends = [
        'proof_image_url',
        'proof_image_urls',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'amount_paid_before' => 'decimal:2',
        'amount_paid_after' => 'decimal:2',
        'remaining_after' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'proof_image' => 'array',
    ];

    /**
     * @return list<string>
     */
    public function getProofImageUrlsAttribute(): array
    {
        $paths = $this->normalizeProofPaths($this->attributes['proof_image'] ?? null);

        return array_values(array_filter(array_map(
            fn (string $path) => $this->resolvePublicUrl($path),
            $paths,
        )));
    }

    public function getProofImageUrlAttribute(): ?string
    {
        return $this->proof_image_urls[0] ?? null;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->approval_status === self::STATUS_REJECTED;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public static function generateReceiptNumber(): string
    {
        $prefix = 'RCP';
        $year = date('Y');
        $month = date('m');

        $last = self::query()
            ->where('receipt_number', 'like', "{$prefix}-{$year}{$month}-%")
            ->orderByDesc('receipt_number')
            ->first();

        $next = $last
            ? ((int) substr($last->receipt_number, -4)) + 1
            : 1;

        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $next);
    }

    /**
     * @return list<string>
     */
    private function normalizeProofPaths(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            return array_values(array_filter(array_map(
                fn ($path) => is_string($path) ? trim($path) : '',
                $raw,
            )));
        }

        if (! is_string($raw)) {
            return [];
        }

        $trimmed = trim($raw);

        if ($trimmed === '') {
            return [];
        }

        if (str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(
                    fn ($path) => is_string($path) ? trim($path) : '',
                    $decoded,
                )));
            }
        }

        return [$trimmed];
    }

    private function resolvePublicUrl(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        return str_starts_with($path, 'http')
            ? $path
            : asset('storage/'.ltrim($path, '/'));
    }
}
