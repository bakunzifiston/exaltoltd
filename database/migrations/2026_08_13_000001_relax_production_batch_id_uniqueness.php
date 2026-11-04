<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('productions')) {
            return;
        }

        // One physical batch is packaged into several products, and each packaging is its own
        // production row. A globally unique batch_id rejected every row after the first, so the
        // uniqueness moves to (batch_id, product_id): batch numbers may repeat across products,
        // but the same product still cannot be recorded twice in one batch.
        if ($this->hasIndexOn(['batch_id'], unique: true)) {
            Schema::table('productions', function (Blueprint $table): void {
                $table->dropUnique(['batch_id']);
            });
        }

        if ($this->hasIndexOn(['batch_id', 'product_id'])) {
            return;
        }

        $this->guardAgainstDuplicates(
            ['batch_id', 'product_id'],
            'Cannot add the (batch_id, product_id) unique index: duplicate pairs exist — ',
        );

        Schema::table('productions', function (Blueprint $table): void {
            $table->unique(['batch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('productions')) {
            return;
        }

        if ($this->hasIndexOn(['batch_id', 'product_id'])) {
            Schema::table('productions', function (Blueprint $table): void {
                $table->dropUnique(['batch_id', 'product_id']);
            });
        }

        if ($this->hasIndexOn(['batch_id'], unique: true)) {
            return;
        }

        // Reversible only while every batch number is still globally unique; once real batches
        // span several packagings the old constraint cannot be restored without losing rows.
        $this->guardAgainstDuplicates(
            ['batch_id'],
            'Cannot restore the batch_id unique index: repeated batch numbers exist — ',
        );

        Schema::table('productions', function (Blueprint $table): void {
            $table->unique('batch_id');
        });
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasIndexOn(array $columns, bool $unique = false): bool
    {
        foreach (Schema::getIndexes('productions') as $index) {
            $indexColumns = array_map(
                static fn ($column) => strtolower((string) $column),
                (array) ($index['columns'] ?? []),
            );

            if ($indexColumns === $columns && (! $unique || ($index['unique'] ?? false))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function guardAgainstDuplicates(array $columns, string $message): void
    {
        $duplicates = DB::table('productions')
            ->select($columns)
            ->groupBy($columns)
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isEmpty()) {
            return;
        }

        $offenders = $duplicates
            ->map(fn ($row) => implode('/', array_map(fn ($column) => (string) $row->{$column}, $columns)))
            ->implode(', ');

        throw new RuntimeException($message.'resolve them first: '.$offenders);
    }
};
