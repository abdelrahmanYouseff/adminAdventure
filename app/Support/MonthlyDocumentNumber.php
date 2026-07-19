<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class MonthlyDocumentNumber
{
    /**
     * مثال: QA-202607100 أو S-202607100
     * العداد يبدأ من 100 كل شهر جديد.
     */
    public static function next(string $prefix, Builder $query, string $column = 'invoice_number'): string
    {
        $period = now()->format('Ym');
        $fullPrefix = $prefix.'-'.$period;
        $lockKey = 'monthly-doc-number:'.$prefix.':'.$period;

        return Cache::lock($lockKey, 10)->block(10, function () use ($query, $column, $fullPrefix, $period, $prefix) {
            $candidates = (clone $query)
                ->where($column, 'like', $fullPrefix.'%')
                ->pluck($column);

            $max = 99;

            foreach ($candidates as $value) {
                if (preg_match('/^'.preg_quote($prefix, '/').'-'.$period.'(\d+)$/', (string) $value, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }

            return $fullPrefix.($max + 1);
        });
    }
}
