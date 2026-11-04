<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links existing transactions to the new subledgers.
 *
 * `expenses` in particular had no payment date at all, so an expense could never appear
 * on a cash flow statement: the system knew what was incurred but not when it was paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->foreignId('cash_account_id')->nullable()->after('method')->constrained('cash_accounts')->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->date('paid_at')->nullable()->after('expense_date');
            $table->string('payment_status', 20)->default('Paid')->after('paid_at'); // Paid | Unpaid
            $table->foreignId('cash_account_id')->nullable()->after('payment_status')->constrained('cash_accounts')->nullOnDelete();
            $table->index('paid_at');
        });

        // Existing expenses are assumed settled on the day they were incurred. Without this
        // they would silently vanish from the operating section of the cash flow statement.
        DB::statement('UPDATE expenses SET paid_at = expense_date WHERE paid_at IS NULL');

        Schema::table('sale_items', function (Blueprint $table): void {
            // FIFO cost frozen at the moment of sale, so gross margin per line is a stored
            // fact rather than a replay of the entire purchase history on every report.
            $table->decimal('unit_cost', 12, 2)->nullable()->after('unit_price');
            $table->decimal('line_cogs', 15, 2)->nullable()->after('line_total');
        });

        Schema::table('inventory_records', function (Blueprint $table): void {
            // getFifoLayers() scans in this exact order on every valuation.
            $table->index(['record_date', 'id'], 'inventory_records_fifo_index');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_records', function (Blueprint $table): void {
            $table->dropIndex('inventory_records_fifo_index');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn(['unit_cost', 'line_cogs']);
        });

        Schema::table('expenses', function (Blueprint $table): void {
            $table->dropForeign(['cash_account_id']);
            $table->dropIndex(['paid_at']);
            $table->dropColumn(['paid_at', 'payment_status', 'cash_account_id']);
        });

        Schema::table('sale_payments', function (Blueprint $table): void {
            $table->dropForeign(['cash_account_id']);
            $table->dropColumn('cash_account_id');
        });
    }
};
