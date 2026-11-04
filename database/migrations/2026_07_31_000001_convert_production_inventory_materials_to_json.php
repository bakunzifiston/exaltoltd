<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TEMPORARY_COLUMN = 'inventory_materials_json';

    public function up(): void
    {
        if (! Schema::hasTable('productions')) {
            return;
        }

        // Recover safely if a previous attempt stopped between dropping and renaming.
        if (! Schema::hasColumn('productions', 'inventory_record_id')) {
            if (Schema::hasColumn('productions', self::TEMPORARY_COLUMN)) {
                Schema::table('productions', function (Blueprint $table): void {
                    $table->renameColumn(self::TEMPORARY_COLUMN, 'inventory_record_id');
                });
            }

            return;
        }

        if ($this->columnAlreadySupportsJson()) {
            return;
        }

        if (! Schema::hasColumn('productions', self::TEMPORARY_COLUMN)) {
            Schema::table('productions', function (Blueprint $table): void {
                $table->json(self::TEMPORARY_COLUMN)->nullable()->after('raw_materials_used');
            });
        }

        $this->backfillMaterialArrays();
        $this->dropInventoryRecordForeignKeys();

        Schema::table('productions', function (Blueprint $table): void {
            $table->dropColumn('inventory_record_id');
        });

        Schema::table('productions', function (Blueprint $table): void {
            $table->renameColumn(self::TEMPORARY_COLUMN, 'inventory_record_id');
        });
    }

    public function down(): void
    {
        // Deliberately irreversible: one integer cannot represent several material lots
        // and their quantities without losing production history.
    }

    private function columnAlreadySupportsJson(): bool
    {
        $type = strtolower(Schema::getColumnType('productions', 'inventory_record_id', true));

        return ! str_contains($type, 'int');
    }

    private function backfillMaterialArrays(): void
    {
        DB::table('productions')
            ->select(['id', 'inventory_record_id', 'raw_materials_used'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $productions): void {
                $fromMovements = $this->materialsFromMovements($productions->pluck('id')->all());

                foreach ($productions as $production) {
                    $materials = $this->decodeMaterials($production->raw_materials_used);

                    if ($materials === []) {
                        $materials = $fromMovements[(int) $production->id] ?? [];
                    }

                    if ($materials === [] && $production->inventory_record_id !== null) {
                        $materials = [[
                            'inventory_id' => (int) $production->inventory_record_id,
                            'quantity_used' => 0,
                        ]];
                    }

                    DB::table('productions')
                        ->where('id', $production->id)
                        ->update([
                            self::TEMPORARY_COLUMN => json_encode(
                                array_values($materials),
                                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
                            ),
                        ]);
                }
            }, 'id');
    }

    /**
     * @param  array<int, int|string>  $productionIds
     * @return array<int, array<int, array{inventory_id: int, quantity_used: float}>>
     */
    private function materialsFromMovements(array $productionIds): array
    {
        if ($productionIds === [] || ! Schema::hasTable('inventory_movements')) {
            return [];
        }

        $rows = DB::table('inventory_movements')
            ->select([
                'reference_id',
                'inventory_record_id',
                DB::raw('SUM(quantity) as net_quantity'),
            ])
            ->where('reference_type', 'production')
            ->whereIn('reference_id', $productionIds)
            ->groupBy('reference_id', 'inventory_record_id')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $quantityUsed = round(-1 * (float) $row->net_quantity, 2);

            if ($quantityUsed > 0) {
                $result[(int) $row->reference_id][] = [
                    'inventory_id' => (int) $row->inventory_record_id,
                    'quantity_used' => $quantityUsed,
                ];
            }
        }

        return $result;
    }

    /**
     * @return array<int, array{inventory_id: int, quantity_used: float|int|string}>
     */
    private function decodeMaterials(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return [];
        }

        $materials = [];

        foreach ($value as $material) {
            if (is_array($material) && ! empty($material['inventory_id'])) {
                $materials[] = [
                    'inventory_id' => (int) $material['inventory_id'],
                    'quantity_used' => $material['quantity_used'] ?? 0,
                ];
            }
        }

        return $materials;
    }

    private function dropInventoryRecordForeignKeys(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $foreignKeys = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_NAME', 'productions')
            ->where('COLUMN_NAME', 'inventory_record_id')
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('CONSTRAINT_NAME');

        foreach ($foreignKeys as $foreignKey) {
            $constraint = (string) $foreignKey;

            if (preg_match('/\A[A-Za-z0-9_$]+\z/', $constraint) !== 1) {
                throw new RuntimeException('Unexpected foreign-key constraint name.');
            }

            DB::statement("ALTER TABLE productions DROP FOREIGN KEY {$constraint}");
        }
    }
};
