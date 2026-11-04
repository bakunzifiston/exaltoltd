<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Move every MyISAM table to InnoDB.
 *
 * The live database was created with MyISAM, which has no transactions, no row locks and
 * no foreign keys. Every DB::transaction() and lockForUpdate() in the application was
 * silently doing nothing: a sale or production batch that failed half-way left its stock
 * deduction behind, and two people saving at once could both take the same stock.
 * InnoDB makes those guarantees real. Only the storage engine changes; the data does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $tables = DB::select("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND engine = 'MyISAM'");
        foreach ($tables as $table) {
            DB::statement('ALTER TABLE `'.str_replace('`', '``', $table->name).'` ENGINE=InnoDB');
        }
    }

    public function down(): void
    {
        // Deliberately not reversed: going back to MyISAM would remove transaction safety.
    }
};
