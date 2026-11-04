<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The comparison picker on the show page queries kind + mode + start_date together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_reports', function (Blueprint $table): void {
            $table->index(['kind', 'mode', 'start_date'], 'financial_reports_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('financial_reports', function (Blueprint $table): void {
            $table->dropIndex('financial_reports_lookup_index');
        });
    }
};
