<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `expense_categories.type` only distinguishes cogs from operating, so interest, tax and
 * financing costs cannot be placed in the right income-statement section. This adds an
 * explicit section without disturbing the existing two-value enum other pages rely on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->string('statement_section', 20)->default('operating')->after('type');
        });

        DB::table('expense_categories')->where('type', 'cogs')->update(['statement_section' => 'costs']);
        DB::table('expense_categories')->where('type', 'operating')->update(['statement_section' => 'operating']);

        // Categories the workbooks treat as below-the-line rather than operating costs.
        DB::table('expense_categories')
            ->whereIn('name', ['Income tax provision', 'Business setup costs', 'Other expenses and taxes'])
            ->update(['statement_section' => 'other']);

        // Interest becomes a derived charge from the loan repayment register.
        DB::table('expense_categories')
            ->whereIn('name', ['Loan interest', 'Loan Interest', 'Interest expense'])
            ->update(['statement_section' => 'interest']);

        // Depreciation becomes a derived charge from the fixed-asset register.
        DB::table('expense_categories')
            ->whereIn('name', ['Depreciation', 'Depreciation / amortisation', 'Amortisation of fixed assets'])
            ->update(['statement_section' => 'depreciation']);
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->dropColumn('statement_section');
        });
    }
};
