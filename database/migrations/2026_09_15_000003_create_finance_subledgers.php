<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The subledgers a balance sheet and a cash flow statement cannot be produced without.
 *
 * Before this, the system recorded revenue, purchases and expenses but had no record of
 * the cash they moved, the assets they bought, the borrowings that funded them or the
 * capital the owner put in. Those are added here as ordinary operational tables;
 * statements are derived from them by query rather than by double-entry posting.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Where money actually sits. `method` on sale_payments was the only previous proxy.
        Schema::create('cash_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('cash'); // cash | bank | mobile_money
            $table->string('account_number')->nullable();
            $table->string('currency', 8)->default('RWF');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->date('opening_balance_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
        });

        // Cash out. Deliberately mirrors sale_payments so both sides read the same way.
        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inventory_record_id')->nullable()->constrained('inventory_records')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('supplier_name')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('method')->default('Cash'); // Cash | MoMo | Bank Transfer | Card | Other
            $table->date('paid_at');
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('paid_at');
            $table->index('method');
        });

        // Replaces the hand-keyed "5,000,000 over 10 years = 41,667/month" workbook line.
        Schema::create('fixed_assets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->date('acquisition_date');
            $table->decimal('cost', 15, 2);
            $table->decimal('salvage_value', 15, 2)->default(0);
            $table->unsignedSmallInteger('useful_life_months');
            $table->string('method', 20)->default('straight_line');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 15, 2)->nullable();
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('acquisition_date');
        });

        Schema::create('loans', function (Blueprint $table): void {
            $table->id();
            $table->string('lender');
            $table->string('reference')->nullable();
            $table->decimal('principal', 15, 2);
            $table->decimal('interest_rate', 8, 4)->default(0);
            $table->string('rate_basis', 20)->default('annual'); // annual | monthly | flat
            $table->date('start_date');
            $table->unsignedSmallInteger('term_months');
            $table->string('repayment_frequency', 20)->default('monthly');
            $table->string('status', 20)->default('active'); // active | settled | written_off
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('start_date');
            $table->index('status');
        });

        // The principal/interest split is what lets one payment feed both the income
        // statement (interest) and the financing section of cash flow (principal).
        Schema::create('loan_repayments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->date('paid_at');
            $table->decimal('total_amount', 15, 2);
            $table->decimal('principal_portion', 15, 2)->default(0);
            $table->decimal('interest_portion', 15, 2)->default(0);
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('paid_at');
        });

        Schema::create('equity_movements', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20); // share_capital | contribution | drawing
            $table->decimal('amount', 15, 2);
            $table->date('occurred_at');
            $table->foreignId('cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete();
            $table->string('description')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('occurred_at');
            $table->index('type');
        });

        // The go-live cut. History stays in the workbooks; this is the bridge to it.
        Schema::create('opening_balances', function (Blueprint $table): void {
            $table->id();
            $table->date('as_of_date');
            $table->string('line_key', 40);
            $table->decimal('amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One figure per line per cut-over date.
            $table->unique(['as_of_date', 'line_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balances');
        Schema::dropIfExists('equity_movements');
        Schema::dropIfExists('loan_repayments');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('cash_accounts');
    }
};
