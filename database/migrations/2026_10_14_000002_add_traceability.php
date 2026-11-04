<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch traceability: date codes, process details and release status on each batch,
 * supplier lot and inspection on each raw-material delivery, quality checks against a
 * batch, and incident (complaint, non-conformance, recall) records.
 *
 * Every batch already made is marked released, so nothing on sale today is held back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('sku', 100)->nullable()->after('type');
            $table->unsignedSmallInteger('shelf_life_days')->nullable()->after('min_order_qty');
            $table->boolean('requires_qc_release')->default(false)->after('shelf_life_days');
        });

        Schema::table('productions', function (Blueprint $table): void {
            $table->date('packaging_date')->nullable()->after('production_date');
            $table->date('best_before_date')->nullable()->after('packaging_date');
            $table->date('expiry_date')->nullable()->after('best_before_date');
            $table->dateTime('started_at')->nullable()->after('expiry_date');
            $table->dateTime('finished_at')->nullable()->after('started_at');
            $table->string('production_line', 100)->nullable()->after('finished_at');
            $table->string('shift', 50)->nullable()->after('production_line');
            $table->string('recipe_version', 100)->nullable()->after('shift');
            $table->string('storage_location', 150)->nullable()->after('recipe_version');
            $table->string('qc_status', 20)->default('released')->after('storage_location');
            $table->foreignId('released_by')->nullable()->after('qc_status')->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable()->after('released_by');
            $table->text('release_notes')->nullable()->after('released_at');

            $table->index(['qc_status', 'best_before_date']);
        });

        Schema::table('inventory_records', function (Blueprint $table): void {
            $table->string('supplier_lot_number', 100)->nullable()->after('lot_number');
            $table->string('inspection_status', 20)->default('approved')->after('supplier_lot_number');
            $table->string('coa_reference', 100)->nullable()->after('inspection_status');

            $table->index('supplier_lot_number');
        });

        Schema::create('batch_quality_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_id')->constrained()->cascadeOnDelete();
            $table->string('check_type', 50);
            $table->decimal('measured_value', 14, 4)->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('spec_min', 14, 4)->nullable();
            $table->decimal('spec_max', 14, 4)->nullable();
            $table->string('result', 10);
            $table->string('lab_report_ref', 100)->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('checked_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['production_id', 'checked_at']);
        });

        Schema::create('trace_incidents', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 30);
            $table->foreignId('production_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->text('description');
            $table->text('actions_taken')->nullable();
            $table->string('disposition', 150)->nullable();
            $table->string('status', 20)->default('open');
            $table->date('opened_at');
            $table->date('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trace_incidents');
        Schema::dropIfExists('batch_quality_checks');

        Schema::table('inventory_records', function (Blueprint $table): void {
            $table->dropIndex(['supplier_lot_number']);
            $table->dropColumn(['supplier_lot_number', 'inspection_status', 'coa_reference']);
        });

        Schema::table('productions', function (Blueprint $table): void {
            $table->dropIndex(['qc_status', 'best_before_date']);
            $table->dropConstrainedForeignId('released_by');
            $table->dropColumn([
                'packaging_date', 'best_before_date', 'expiry_date', 'started_at', 'finished_at', 'production_line',
                'shift', 'recipe_version', 'storage_location', 'qc_status', 'released_at', 'release_notes',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['sku', 'shelf_life_days', 'requires_qc_release']);
        });
    }
};
