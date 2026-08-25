<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes for common filters/lookups.
     * Does not change columns, data, or relationships.
     */
    public function up(): void
    {
        $orderIndexes = $this->indexNames('orders');

        Schema::table('orders', function (Blueprint $table) use ($orderIndexes) {
            if (! in_array('orders_status_index', $orderIndexes, true)) {
                $table->index('status', 'orders_status_index');
            }

            if (! in_array('orders_payment_status_index', $orderIndexes, true)) {
                $table->index('payment_status', 'orders_payment_status_index');
            }
        });

        $userIndexes = $this->indexNames('users');

        Schema::table('users', function (Blueprint $table) use ($userIndexes) {
            if (! in_array('users_phone_index', $userIndexes, true)) {
                $table->index('phone', 'users_phone_index');
            }
        });
    }

    public function down(): void
    {
        $orderIndexes = $this->indexNames('orders');

        Schema::table('orders', function (Blueprint $table) use ($orderIndexes) {
            if (in_array('orders_status_index', $orderIndexes, true)) {
                $table->dropIndex('orders_status_index');
            }

            if (in_array('orders_payment_status_index', $orderIndexes, true)) {
                $table->dropIndex('orders_payment_status_index');
            }
        });

        $userIndexes = $this->indexNames('users');

        Schema::table('users', function (Blueprint $table) use ($userIndexes) {
            if (in_array('users_phone_index', $userIndexes, true)) {
                $table->dropIndex('users_phone_index');
            }
        });
    }

    /**
     * @return list<string>
     */
    private function indexNames(string $table): array
    {
        return array_values(array_filter(array_map(
            static fn (array $index): ?string => $index['name'] ?? null,
            Schema::getIndexes($table),
        )));
    }
};
