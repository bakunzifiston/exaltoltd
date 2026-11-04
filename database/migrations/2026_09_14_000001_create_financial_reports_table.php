<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('kind', 30);
            $table->string('mode', 20);
            $table->string('source', 30);
            $table->date('start_date');
            $table->date('as_of')->nullable();
            $table->string('prepared_by');
            $table->text('notes')->nullable();
            $table->json('rows');
            $table->json('source_notes');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reports');
    }
};
