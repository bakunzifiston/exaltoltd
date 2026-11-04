<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock returns from customers and production samples given away.
 *
 * A return credits the original sale (sales.returned_amount) instead of creating a second
 * financial record, and records what came back per sale line (sale_items.quantity_returned).
 * Samples leave finished-goods stock at cost without ever becoming a sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('returned_amount', 12, 2)->default(0)->after('amount_paid');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->decimal('quantity_returned', 10, 2)->default(0)->after('quantity_sold');
        });

        Schema::create('stock_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name');
            $table->string('delivery_note', 100)->nullable();
            $table->date('return_date');
            $table->string('reason', 30);
            $table->string('reason_other', 255)->nullable();
            $table->string('status', 30)->default('draft');
            $table->decimal('total_value', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'return_date']);
            $table->index('customer_id');
        });

        Schema::create('stock_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity_delivered', 10, 2);
            $table->decimal('quantity_sold_reported', 10, 2)->default(0);
            $table->decimal('quantity_returned', 10, 2);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_value', 12, 2);
            // Filled in at inspection; together they account for quantity_returned.
            $table->decimal('quantity_restocked', 10, 2)->default(0);
            $table->decimal('quantity_damaged', 10, 2)->default(0);
            $table->decimal('quantity_expired', 10, 2)->default(0);
            $table->decimal('quantity_rejected', 10, 2)->default(0);
            $table->string('condition_notes', 255)->nullable();
            $table->timestamps();

            $table->index('sale_item_id');
            $table->index('production_id');
        });

        Schema::create('production_samples', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->date('sample_date');
            $table->string('recipient_type', 30);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name');
            $table->string('purpose', 255);
            $table->string('requested_by_name')->nullable();
            $table->string('received_by_name')->nullable();
            $table->string('status', 30)->default('pending_approval');
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->decimal('cost_value', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->text('decision_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'sample_date']);
            $table->index('production_id');
        });

        Schema::create('transaction_documents', function (Blueprint $table): void {
            $table->id();
            $table->morphs('documentable');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('record_events', function (Blueprint $table): void {
            $table->id();
            $table->morphs('recordable');
            $table->string('event', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('details')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_events');
        Schema::dropIfExists('transaction_documents');
        Schema::dropIfExists('production_samples');
        Schema::dropIfExists('stock_return_items');
        Schema::dropIfExists('stock_returns');

        Schema::table('sale_items', fn (Blueprint $table) => $table->dropColumn('quantity_returned'));
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('returned_amount'));
    }
};
