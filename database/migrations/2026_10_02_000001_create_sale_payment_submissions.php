<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payments that are claimed but not yet confirmed.
 *
 * `sale_payments` is read as money actually received by cash flow, the balance sheet, the
 * dashboard and team performance. A payment a sales person reports therefore waits here, with
 * its proof, until someone allowed to approve payments confirms it; only then is a
 * `sale_payments` row written. Rejected or withdrawn claims never reach the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method')->default('Cash'); // SalePayment::METHODS
            $table->date('paid_at');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending | approved | rejected | withdrawn | reversed
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['sale_id', 'status']);
            $table->index('reference');
        });

        Schema::create('sale_payment_proofs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('submission_id')->constrained('sale_payment_submissions')->cascadeOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payment_proofs');
        Schema::dropIfExists('sale_payment_submissions');
    }
};
