<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated baseline schema.
 *
 * Replaces the original incremental migration history, which had several migrations
 * dated BEFORE the create-table migrations they altered — on a fresh database those
 * alters silently no-op'd, leaving columns missing. Tables here are created in
 * foreign-key dependency order with every column in its final shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ------------------------------------------------------------------
        // Framework / infrastructure tables
        // ------------------------------------------------------------------
        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        // ------------------------------------------------------------------
        // Independent domain tables (no outbound foreign keys)
        // ------------------------------------------------------------------
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('photo')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->unique();
            $table->string('description')->nullable();
            $table->string('barcode')->unique();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('compare_at_price', 10, 2)->nullable();
            $table->unsignedInteger('min_order_qty')->default(5);
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('source')->default('admin'); // admin | storefront | backfill
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('phone');
            $table->index('email');
            $table->index('name');
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->enum('type', ['cogs', 'operating']);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('partners', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->string('website_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->timestamps();
        });

        Schema::create('storefront_videos', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('video_path');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('storefront_gallery_albums', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('storefront_gallery_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('album_id')->nullable()->constrained('storefront_gallery_albums')->cascadeOnDelete();
            $table->string('title');
            $table->string('image_path');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group', 50)->default('general');
            $table->string('type', 30)->default('text');
            $table->timestamps();
        });

        Schema::create('contact_channels', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 30); // phone | email | whatsapp | address | social
            $table->string('label');
            $table->text('value');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_active', 'sort_order']);
        });

        Schema::create('email_routing_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('event', 60); // contact_form | order_placed | order_status_update
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['event', 'is_active']);
        });

        Schema::create('site_images', function (Blueprint $table): void {
            $table->id();
            $table->string('context', 50)->default('general'); // logo | about | contact | hero | general
            $table->string('label');
            $table->string('path');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['context', 'is_active', 'sort_order']);
        });

        // ------------------------------------------------------------------
        // users (references employees)
        // ------------------------------------------------------------------
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('role', 30)->default('sales');
            $table->boolean('is_active')->default(true);
            $table->string('phone')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('sales_target', 12, 2)->nullable();  // monthly RWF target
            $table->decimal('commission_rate', 5, 2)->nullable(); // percent of sales
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // ------------------------------------------------------------------
        // inventory_records (references suppliers, products, users)
        // ------------------------------------------------------------------
        Schema::create('inventory_records', function (Blueprint $table): void {
            $table->id();
            $table->string('supplier_name');
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number')->nullable();
            $table->enum('item_type', ['Product', 'Raw Material']);
            $table->string('item_name');
            $table->string('lot_number')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity_in', 10, 2);
            $table->decimal('quantity_out', 10, 2);
            $table->decimal('damaged', 10, 2);
            $table->string('storage_location');
            $table->date('record_date');
            $table->date('expiry_date')->nullable();
            $table->decimal('reorder_level', 12, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->enum('payment_status', ['Paid', 'Partial', 'Unpaid'])->default('Unpaid');
            $table->date('payment_due_date')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('record_date');
            $table->index('payment_status');
        });

        // ------------------------------------------------------------------
        // productions (references products, inventory_records, employees, users)
        // ------------------------------------------------------------------
        Schema::create('productions', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_id')->unique();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('barcode')->nullable();
            $table->decimal('quantity_produced', 10, 2);
            $table->decimal('quantity_remaining', 10, 2)->default(0);
            $table->decimal('damaged', 10, 2);
            $table->json('raw_materials_used')->nullable();
            // A batch can consume several inventory lots, each with its own quantity.
            $table->json('inventory_record_id')->nullable();
            $table->date('production_date');
            $table->string('responsible_staff')->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('quality_control_notes')->nullable();
            $table->timestamps();

            $table->index('employee_id');
            $table->index('created_by');
            $table->index('production_date');
        });

        // ------------------------------------------------------------------
        // sales (references products, productions, customers, users)
        // ------------------------------------------------------------------
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->string('sales_id')->unique();
            $table->string('barcode')->nullable();
            $table->string('customer_name');
            $table->string('customer_Phone')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_email')->nullable();
            $table->string('delivery_address')->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('production_id')->nullable()->constrained('productions')->onDelete('cascade');
            $table->decimal('quantity_sold', 10, 2)->nullable();
            $table->decimal('selling_price', 10, 2)->nullable();
            $table->decimal('total_revenue', 12, 2);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->string('payment_status', 30)->default('Pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('delivery_status', 30)->default('Pending');
            $table->timestamp('delivered_at')->nullable();
            $table->string('sales_channel');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_number')->nullable();
            $table->date('sale_date');
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index('created_by');
            $table->index('sale_date');
            $table->index('payment_status');
            $table->index('sales_channel');
            $table->index('due_date');
        });

        // ------------------------------------------------------------------
        // Tables that reference sales
        // ------------------------------------------------------------------
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('production_id')->constrained('productions')->onDelete('cascade');
            $table->decimal('quantity_sold', 10, 2);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method')->default('Cash'); // Cash | MoMo | Bank Transfer | Card | Other
            $table->date('paid_at');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_backfilled')->default(false);
            $table->timestamps();

            $table->index('paid_at');
            $table->index('method');
        });

        Schema::create('sale_status_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('field'); // payment_status | delivery_status
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sale_id', 'field']);
        });

        // ------------------------------------------------------------------
        // Movement ledgers
        // ------------------------------------------------------------------
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('production_id')->nullable()->constrained('productions')->nullOnDelete();
            $table->string('type'); // produced | sold | sale_reversal | adjustment | damaged
            $table->decimal('quantity', 12, 2); // signed: + into stock, - out of stock
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at');
            $table->timestamps();

            $table->index(['product_id', 'type']);
            $table->index('moved_at');
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_record_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // intake | consumption | damage | adjustment | reversal
            $table->decimal('quantity', 12, 2); // signed: + into stock, - out of stock
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at');
            $table->timestamps();

            $table->index(['inventory_record_id', 'type']);
            $table->index('moved_at');
        });

        // ------------------------------------------------------------------
        // expenses (references expense_categories, users)
        // ------------------------------------------------------------------
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 8)->default('RWF');
            $table->date('expense_date');
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['year', 'month']);
            $table->index('expense_date');
        });

        // ------------------------------------------------------------------
        // Seed reference data
        // ------------------------------------------------------------------
        $now = now();
        DB::table('partners')->insert([
            ['name' => 'SIMBA', 'is_active' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'T2000', 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Deluxe Supermarket', 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        // Drop in reverse dependency order.
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('sale_status_events');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('productions');
        Schema::dropIfExists('inventory_records');
        Schema::dropIfExists('users');
        Schema::dropIfExists('site_images');
        Schema::dropIfExists('email_routing_rules');
        Schema::dropIfExists('contact_channels');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('storefront_gallery_photos');
        Schema::dropIfExists('storefront_gallery_albums');
        Schema::dropIfExists('storefront_videos');
        Schema::dropIfExists('contact_submissions');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
    }
};
