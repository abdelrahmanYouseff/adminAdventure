<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderLog;
use App\Models\OrderPaymentReceipt;
use App\Models\WorkerOrderNote;

class OrderNotePurger
{
    public static function deleteActivityNote(Order $order, WorkerOrderNote $note): void
    {
        $body = trim((string) $note->body);
        $note->delete();

        if ($body !== '') {
            self::purgeText($order, $body);
        }
    }

    public static function deleteOrderFieldNote(Order $order): void
    {
        $body = trim((string) ($order->notes ?? ''));
        self::purgeText($order, $body);
        self::clearOrderNotes($order);
    }

    public static function purgeText(Order $order, string $body): void
    {
        $body = trim($body);

        if ($body === '') {
            return;
        }

        WorkerOrderNote::query()
            ->where('order_id', $order->id)
            ->where('body', $body)
            ->delete();

        $currentOrderNotes = trim((string) ($order->notes ?? ''));
        if ($currentOrderNotes !== '') {
            $next = self::stripBody($currentOrderNotes, $body);
            if ($next !== $currentOrderNotes) {
                $order->notes = $next === '' ? null : $next;
                $order->saveQuietly();
            }
        }

        OrderPaymentReceipt::query()
            ->where('order_id', $order->id)
            ->whereNotNull('notes')
            ->get()
            ->each(function (OrderPaymentReceipt $receipt) use ($body): void {
                $current = trim((string) $receipt->notes);
                if ($current === '') {
                    return;
                }

                $next = self::stripBody($current, $body);
                if ($next === $current) {
                    return;
                }

                $receipt->notes = $next === '' ? null : $next;
                $receipt->save();
            });

        OrderLog::query()
            ->where('order_id', $order->id)
            ->whereNotNull('changes')
            ->get()
            ->each(function (OrderLog $log) use ($body): void {
                $changes = $log->changes ?? [];
                if (! is_array($changes) || $changes === []) {
                    return;
                }

                $updated = self::redactChanges($changes, $body);
                if ($updated === $changes) {
                    return;
                }

                $log->changes = $updated === [] ? null : $updated;
                $log->save();
            });
    }

    private static function clearOrderNotes(Order $order): void
    {
        if ($order->notes === null || trim((string) $order->notes) === '') {
            if ($order->notes !== null) {
                $order->notes = null;
                $order->saveQuietly();
            }

            return;
        }

        $order->notes = null;
        $order->saveQuietly();
    }

    private static function stripBody(string $text, string $body): string
    {
        if (trim($text) === $body) {
            return '';
        }

        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $kept = array_values(array_filter(
            $lines,
            fn (string $line): bool => trim($line) !== $body,
        ));

        return trim(implode("\n", $kept));
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private static function redactChanges(array $changes, string $body): array
    {
        foreach ($changes as $key => $value) {
            if (! is_array($value)) {
                if (is_string($value) && self::containsBody($value, $body)) {
                    unset($changes[$key]);
                }

                continue;
            }

            $from = $value['from'] ?? null;
            $to = $value['to'] ?? null;
            $fromMatch = is_string($from) && self::containsBody($from, $body);
            $toMatch = is_string($to) && self::containsBody($to, $body);

            if (! $fromMatch && ! $toMatch) {
                continue;
            }

            if ($fromMatch) {
                $value['from'] = self::stripBody($from, $body);
                if ($value['from'] === '') {
                    $value['from'] = null;
                }
            }

            if ($toMatch) {
                $value['to'] = self::stripBody($to, $body);
                if ($value['to'] === '') {
                    $value['to'] = null;
                }
            }

            if (($value['from'] ?? null) === null && ($value['to'] ?? null) === null) {
                unset($changes[$key]);
            } else {
                $changes[$key] = $value;
            }
        }

        return $changes;
    }

    private static function containsBody(string $text, string $body): bool
    {
        if (trim($text) === $body) {
            return true;
        }

        foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
            if (trim((string) $line) === $body) {
                return true;
            }
        }

        return false;
    }
}
