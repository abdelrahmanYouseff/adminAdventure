<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add 'processing' to orders.status enum (MySQL).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'processing', 'paid', 'cancelled', 'refunded') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optional: revert to original enum (only if no rows use 'processing')
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'paid', 'cancelled', 'refunded') DEFAULT 'pending'");
    }
};
