<?php

namespace Database\Factories;

use App\Models\FinancialReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FinancialReport>
 */
class FinancialReportFactory extends Factory
{
    protected $model = FinancialReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'FY '.now()->year.' draft',
            'kind' => FinancialReport::KIND_INCOME_STATEMENT,
            'mode' => FinancialReport::MODE_ACTUAL,
            'source' => FinancialReport::SOURCE_MANUAL,
            'start_date' => now()->startOfYear()->toDateString(),
            'as_of' => null,
            'prepared_by' => fake()->name(),
            'notes' => null,
            'rows' => [],
            'source_notes' => [],
            'created_by' => null,
        ];
    }

    public function kind(string $kind): static
    {
        return $this->state(fn () => ['kind' => $kind]);
    }

    public function forecast(): static
    {
        return $this->state(fn () => ['mode' => FinancialReport::MODE_FORECAST]);
    }

    /** A system snapshot is always an actual with a cutoff date. */
    public function snapshot(?string $asOf = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => FinancialReport::SOURCE_SYSTEM,
            'mode' => FinancialReport::MODE_ACTUAL,
            'as_of' => $asOf ?? $attributes['start_date'],
        ]);
    }

    /** Fill every row with the same value across all columns, for arithmetic fixtures. */
    public function withRows(array $rows): static
    {
        return $this->state(fn () => ['rows' => $rows]);
    }
}
