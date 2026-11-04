<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who may confirm that a reported payment was actually received. The owner always can;
 * everyone else is opted in per user from the Users screen. Managers start opted in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_approve_payments')->default(false)->after('is_active');
        });

        DB::table('users')->where('role', 'manager')->update(['can_approve_payments' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('can_approve_payments');
        });
    }
};
