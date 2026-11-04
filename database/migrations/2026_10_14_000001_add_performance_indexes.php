<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the filters the statements, dashboards and traceability run most.
 *
 * MySQL indexes a foreign key column automatically; SQLite and Postgres do not, so the
 * sale_items and sale_payments keys are indexed explicitly. Each index is skipped when an
 * equivalent already exists, so this runs cleanly on any of the three.
 */
return new class extends Migration
{
    private const INDEXES = [
        'sales' => [['delivery_status', 'sale_date']],
        'sale_items' => [['production_id'], ['sale_id']],
        'sale_payments' => [['sale_id']],
        'inventory_movements' => [['reference_type', 'reference_id']],
        'stock_movements' => [['reference_type', 'reference_id']],
        'inventory_records' => [['item_name']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                if (Schema::hasTable($table) && ! Schema::hasIndex($table, $columns)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $this->name($table, $columns)));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $columns) {
                if (Schema::hasTable($table) && Schema::hasIndex($table, $this->name($table, $columns))) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($this->name($table, $columns)));
                }
            }
        }
    }

    private function name(string $table, array $columns): string
    {
        return 'perf_'.$table.'_'.implode('_', $columns).'_index';
    }
};
