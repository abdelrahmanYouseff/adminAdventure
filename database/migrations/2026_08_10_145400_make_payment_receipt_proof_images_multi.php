<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('order_payment_receipts', 'proof_image')) {
            return;
        }

        // Allow storing a JSON array of image paths.
        DB::statement('ALTER TABLE order_payment_receipts MODIFY proof_image TEXT NULL');

        $rows = DB::table('order_payment_receipts')
            ->whereNotNull('proof_image')
            ->where('proof_image', '!=', '')
            ->get(['id', 'proof_image']);

        foreach ($rows as $row) {
            $raw = trim((string) $row->proof_image);

            if ($raw === '' || str_starts_with($raw, '[')) {
                continue;
            }

            DB::table('order_payment_receipts')
                ->where('id', $row->id)
                ->update([
                    'proof_image' => json_encode([$raw], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('order_payment_receipts', 'proof_image')) {
            return;
        }

        $rows = DB::table('order_payment_receipts')
            ->whereNotNull('proof_image')
            ->where('proof_image', '!=', '')
            ->get(['id', 'proof_image']);

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row->proof_image, true);
            $first = is_array($decoded) ? ($decoded[0] ?? null) : $row->proof_image;

            DB::table('order_payment_receipts')
                ->where('id', $row->id)
                ->update([
                    'proof_image' => is_string($first) ? mb_substr($first, 0, 255) : null,
                ]);
        }

        DB::statement('ALTER TABLE order_payment_receipts MODIFY proof_image VARCHAR(255) NULL');
    }
};
