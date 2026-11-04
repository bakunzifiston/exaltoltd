<?php

namespace App\Http\Requests\Admin\Concerns;

trait ValidatesTraceabilityFields
{
    /** Date codes and process details recorded on a production batch. */
    protected function traceabilityRules(): array
    {
        return [
            'packaging_date' => ['nullable', 'date', 'after_or_equal:production_date'],
            'best_before_date' => ['nullable', 'date', 'after_or_equal:production_date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:production_date'],
            'started_at' => ['nullable', 'date'],
            'finished_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'production_line' => ['nullable', 'string', 'max:100'],
            'shift' => ['nullable', 'string', 'max:50'],
            'recipe_version' => ['nullable', 'string', 'max:100'],
            'storage_location' => ['nullable', 'string', 'max:150'],
        ];
    }
}
